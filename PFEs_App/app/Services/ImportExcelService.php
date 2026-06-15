<?php

namespace App\Services;

use App\Models\Etudiant;
use App\Models\Pfe;
use App\Models\Professeur;
use App\Models\Salle;
use App\Models\Soutenance;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportExcelService
{
    public function analyserWorkbook(UploadedFile $file): array
    {
        $prepared = $this->prepareWorkbook($file);

        return [
            'pfes_count' => count($prepared['etudiants']['data']),
            'professeurs_count' => count($prepared['professeurs']['data']),
            'salles_count' => count($prepared['salles']['data']),
            'salles_disponibles' => collect($prepared['salles']['data'])
                ->filter(fn ($salle) => (bool) $salle['disponible'])
                ->pluck('nom')
                ->values()
                ->all(),
            'errors' => $prepared['errors'],
        ];
    }

    public function importWorkbook(UploadedFile $file): array
    {
        $prepared = $this->prepareWorkbook($file);

        if (!empty($prepared['errors'])) {
            return $this->emptyResult($prepared['errors']);
        }

        try {
            return DB::transaction(function () use ($prepared) {
                Soutenance::query()->delete();
                Pfe::query()->delete();
                Etudiant::query()->delete();
                Professeur::query()->delete();
                Salle::query()->delete();

                foreach ($prepared['salles']['data'] as $data) {
                    Salle::create($data);
                }

                foreach ($prepared['professeurs']['data'] as $data) {
                    Professeur::create($data);
                }

                foreach ($prepared['etudiants']['data'] as $item) {
                    $etudiant = Etudiant::create([
                        'nom' => $item['nom'],
                        'prenom' => $item['prenom'],
                        'cne' => $item['cne'],
                        'email' => $item['email'],
                        'filiere' => $item['filiere'],
                    ]);

                    Pfe::create([
                        'sujet' => $item['sujet'],
                        'langue' => $item['langue'],
                        'id_etudiant' => $etudiant->id_etudiant,
                        'id_encadrant' => null,
                    ]);
                }

                return [
                    'students_imported' => count($prepared['etudiants']['data']),
                    'pfes_imported' => count($prepared['etudiants']['data']),
                    'professeurs_imported' => count($prepared['professeurs']['data']),
                    'salles_imported' => count($prepared['salles']['data']),
                    'errors' => [],
                ];
            });
        } catch (\Throwable $e) {
            return $this->emptyResult([$e->getMessage()]);
        }
    }

    public function validateRow(array $data, array $rules)
    {
        return Validator::make($data, $rules);
    }

    private function prepareWorkbook(UploadedFile $file): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Throwable $e) {
            return [
                'professeurs' => ['data' => [], 'errors' => []],
                'etudiants' => ['data' => [], 'errors' => []],
                'salles' => ['data' => [], 'errors' => []],
                'errors' => ["Impossible de lire le fichier Excel : {$e->getMessage()}"],
            ];
        }

        $professeursSheet = $this->findSheet($spreadsheet, ['professeur', 'professeurs', 'enseignant', 'enseignants']);
        $etudiantsSheet = $this->findSheet($spreadsheet, ['etudiant', 'etudiants', 'student', 'students']);
        $sallesSheet = $this->findSheet($spreadsheet, ['salle', 'salles', 'classroom', 'classrooms']);

        $missingErrors = [];

        if (!$professeursSheet || !$etudiantsSheet || !$sallesSheet) {
            $missingErrors[] = 'Le fichier Excel doit contenir trois feuilles nommees : professeurs, etudiants, salles.';
            $missingErrors[] = 'Noms de feuilles trouves : ' . implode(', ', $this->sheetNames($spreadsheet)) . '.';
        }

        if (!$professeursSheet) {
            $missingErrors[] = 'La feuille professeurs est introuvable.';
        }

        if (!$etudiantsSheet) {
            $missingErrors[] = 'La feuille etudiants est introuvable.';
        }

        if (!$sallesSheet) {
            $missingErrors[] = 'La feuille salles est introuvable.';
        }

        if (!empty($missingErrors)) {
            return [
                'professeurs' => ['data' => [], 'errors' => []],
                'etudiants' => ['data' => [], 'errors' => []],
                'salles' => ['data' => [], 'errors' => []],
                'errors' => $missingErrors,
            ];
        }

        $professeurs = $this->prepareProfesseurs($professeursSheet);
        $etudiants = $this->prepareEtudiants($etudiantsSheet);
        $salles = $this->prepareSalles($sallesSheet);

        return [
            'professeurs' => $professeurs,
            'etudiants' => $etudiants,
            'salles' => $salles,
            'errors' => array_merge(
                $professeurs['errors'],
                $etudiants['errors'],
                $salles['errors']
            ),
        ];
    }

    private function prepareEtudiants(Worksheet $sheet): array
    {
        $errors = [];
        $dataToImport = [];
        $seenCNEs = [];
        $seenEmails = [];

        foreach ($sheet->toArray() as $index => $row) {
            if ($this->shouldSkipRow($row, $index, ['cne', 'nom', 'prenom', 'email', 'filiere', 'sujet', 'langue'])) {
                continue;
            }

            $lineNum = $index + 1;
            $hasAcademicEmailColumn = trim((string) ($row[7] ?? '')) !== '';

            $data = [
                'cne' => trim((string) ($row[0] ?? '')),
                'nom' => trim((string) ($row[1] ?? '')),
                'prenom' => trim((string) ($row[2] ?? '')),
                'email' => trim((string) ($hasAcademicEmailColumn ? ($row[4] ?? '') : ($row[3] ?? ''))),
                'filiere' => trim((string) ($hasAcademicEmailColumn ? ($row[5] ?? '') : ($row[4] ?? ''))),
                'sujet' => trim((string) ($hasAcademicEmailColumn ? ($row[6] ?? '') : ($row[5] ?? ''))),
                'langue' => trim((string) ($hasAcademicEmailColumn ? ($row[7] ?? '') : ($row[6] ?? ''))),
            ];

            $validator = $this->validateRow($data, [
                'cne' => 'required|string',
                'nom' => 'required|string',
                'prenom' => 'required|string',
                'email' => 'required|email',
                'filiere' => 'required|string',
                'sujet' => 'required|string',
                'langue' => 'required|string',
            ]);

            if ($validator->fails()) {
                $missingFields = implode(', ', $validator->errors()->keys());
                $errors[] = "Feuille etudiant, ligne {$lineNum} : donnees manquantes ou invalides ({$missingFields}).";
                continue;
            }

            $cneKey = strtoupper($data['cne']);
            $emailKey = strtolower($data['email']);

            if (in_array($cneKey, $seenCNEs, true)) {
                $errors[] = "Feuille etudiant, ligne {$lineNum} : le CNE {$data['cne']} est duplique.";
                continue;
            }

            if (in_array($emailKey, $seenEmails, true)) {
                $errors[] = "Feuille etudiant, ligne {$lineNum} : l'email {$data['email']} est duplique.";
                continue;
            }

            $seenCNEs[] = $cneKey;
            $seenEmails[] = $emailKey;
            $dataToImport[] = $data;
        }

        if (empty($dataToImport)) {
            $errors[] = 'La feuille etudiant ne contient aucune ligne valide.';
        }

        return [
            'data' => $dataToImport,
            'errors' => $errors,
        ];
    }

    private function prepareProfesseurs(Worksheet $sheet): array
    {
        $errors = [];
        $dataToImport = [];
        $seenProfessors = [];

        foreach ($sheet->toArray() as $index => $row) {
            if ($this->shouldSkipRow($row, $index, ['nom', 'prenom', 'specialite'])) {
                continue;
            }

            $lineNum = $index + 1;
            $data = [
                'nom' => trim((string) ($row[0] ?? '')),
                'prenom' => trim((string) ($row[1] ?? '')),
                'specialite' => trim((string) ($row[2] ?? '')),
            ];

            $validator = $this->validateRow($data, [
                'nom' => 'required|string',
                'prenom' => 'required|string',
                'specialite' => 'required|string',
            ]);

            if ($validator->fails()) {
                $missingFields = implode(', ', $validator->errors()->keys());
                $errors[] = "Feuille professeur, ligne {$lineNum} : donnees manquantes ou invalides ({$missingFields}).";
                continue;
            }

            $profKey = strtoupper($data['nom'] . '|' . $data['prenom']);

            if (in_array($profKey, $seenProfessors, true)) {
                $errors[] = "Feuille professeur, ligne {$lineNum} : le professeur {$data['nom']} {$data['prenom']} est duplique.";
                continue;
            }

            $seenProfessors[] = $profKey;
            $dataToImport[] = $data;
        }

        if (empty($dataToImport)) {
            $errors[] = 'La feuille professeur ne contient aucune ligne valide.';
        }

        return [
            'data' => $dataToImport,
            'errors' => $errors,
        ];
    }

    private function prepareSalles(Worksheet $sheet): array
    {
        $errors = [];
        $dataToImport = [];
        $seenSalles = [];

        foreach ($sheet->toArray() as $index => $row) {
            if ($this->shouldSkipRow($row, $index, ['salle', 'nom', 'disponible'])) {
                continue;
            }

            $lineNum = $index + 1;
            $data = [
                'nom' => trim((string) ($row[0] ?? '')),
                'disponible' => $this->parseDisponible($row[1] ?? null),
            ];

            $validator = $this->validateRow($data, [
                'nom' => 'required|string',
                'disponible' => 'boolean',
            ]);

            if ($validator->fails()) {
                $missingFields = implode(', ', $validator->errors()->keys());
                $errors[] = "Feuille salle, ligne {$lineNum} : donnees manquantes ou invalides ({$missingFields}).";
                continue;
            }

            $salleKey = strtoupper($data['nom']);

            if (in_array($salleKey, $seenSalles, true)) {
                $errors[] = "Feuille salle, ligne {$lineNum} : la salle {$data['nom']} est dupliquee.";
                continue;
            }

            $seenSalles[] = $salleKey;
            $dataToImport[] = $data;
        }

        if (empty($dataToImport)) {
            $errors[] = 'La feuille salle ne contient aucune ligne valide.';
        }

        return [
            'data' => $dataToImport,
            'errors' => $errors,
        ];
    }

    private function findSheet(Spreadsheet $spreadsheet, array $aliases): ?Worksheet
    {
        $normalizedAliases = array_map(fn ($alias) => $this->normalizeLabel($alias), $aliases);

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $title = $this->normalizeLabel($sheet->getTitle());

            if (in_array($title, $normalizedAliases, true)) {
                return $sheet;
            }
        }

        return null;
    }

    private function sheetNames(Spreadsheet $spreadsheet): array
    {
        $names = [];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $names[] = $sheet->getTitle();
        }

        return $names;
    }

    private function shouldSkipRow(array $row, int $index, array $headerKeywords): bool
    {
        $values = array_values(array_filter(array_map(
            fn ($value) => trim((string) $value),
            $row
        ), fn ($value) => $value !== ''));

        if (empty($values)) {
            return true;
        }

        $normalizedValues = array_map(fn ($value) => $this->normalizeLabel($value), $values);
        $normalizedHeaders = array_map(fn ($value) => $this->normalizeLabel($value), $headerKeywords);

        if ($index === 0 || count(array_intersect($normalizedValues, $normalizedHeaders)) >= 2) {
            return true;
        }

        return false;
    }

    private function parseDisponible($value): bool
    {
        $value = $this->normalizeLabel((string) $value);

        if ($value === '') {
            return true;
        }

        return !in_array($value, ['0', 'false', 'faux', 'non', 'no', 'indisponible'], true);
    }

    private function normalizeLabel(string $value): string
    {
        $value = trim($value);
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        if ($converted !== false) {
            $value = $converted;
        }

        $value = strtolower($value);

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }

    private function emptyResult(array $errors): array
    {
        return [
            'students_imported' => 0,
            'pfes_imported' => 0,
            'professeurs_imported' => 0,
            'salles_imported' => 0,
            'errors' => $errors,
        ];
    }
}
