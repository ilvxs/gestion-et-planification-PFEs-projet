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
                        'sujet' => $item['sujet'] ?? null,
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

        $rows = $sheet->toArray();

        $requiredColumns = [
            'cne' => ['cne'],
            'nom' => ['nom'],
            'prenom' => ['prenom', 'prénom'],
            'email' => [
                'email',
                'mail',
                'email academique',
                'email académique',
                'mail academique',
                'mail académique',
                'email personnel',
                'mail personnel'
            ],
            'filiere' => ['filiere', 'filière'],
            'langue' => ['langue', 'language'],
        ];

        $optionalColumns = [
            'sujet' => ['sujet', 'theme', 'thème', 'titre', 'intitule', 'intitulé'],
            'email_academique' => [
                'email academique',
                'email académique',
                'mail academique',
                'mail académique'
            ],
            'email_personnel' => [
                'email personnel',
                'mail personnel'
            ],
        ];

        $headerIndex = $this->findHeaderRowIndex($rows, $requiredColumns);

        if ($headerIndex === null) {
            return [
                'data' => [],
                'errors' => [
                    "Feuille etudiant : ligne d'entete introuvable. Colonnes obligatoires : CNE, nom, prenom, email, filiere, langue."
                ],
            ];
        }

        $columns = $this->buildColumnMap($rows[$headerIndex]);

        $missingColumns = $this->missingRequiredColumns($columns, $requiredColumns);

        if (!empty($missingColumns)) {
            return [
                'data' => [],
                'errors' => [
                    'Feuille etudiant : colonnes obligatoires manquantes : ' . implode(', ', $missingColumns) . '.'
                ],
            ];
        }

        foreach ($rows as $index => $row) {
            if ($index <= $headerIndex || $this->isEmptyRow($row)) {
                continue;
            }

            $lineNum = $index + 1;

            $emailAcademique = $this->getCellByAliases($row, $columns, $optionalColumns['email_academique']);
            $emailPersonnel = $this->getCellByAliases($row, $columns, $optionalColumns['email_personnel']);
            $emailGeneral = $this->getCellByAliases($row, $columns, ['email', 'mail']);

            $email = $emailAcademique ?: ($emailGeneral ?: $emailPersonnel);

            $sujet = $this->getCellByAliases($row, $columns, $optionalColumns['sujet']);

            $data = [
                'cne' => $this->getCellByAliases($row, $columns, $requiredColumns['cne']),
                'nom' => $this->getCellByAliases($row, $columns, $requiredColumns['nom']),
                'prenom' => $this->getCellByAliases($row, $columns, $requiredColumns['prenom']),
                'email' => $email,
                'filiere' => $this->getCellByAliases($row, $columns, $requiredColumns['filiere']),
                'sujet' => $sujet !== '' ? $sujet : null,
                'langue' => $this->getCellByAliases($row, $columns, $requiredColumns['langue']),
            ];

            $validator = $this->validateRow($data, [
                'cne' => 'required|string',
                'nom' => 'required|string',
                'prenom' => 'required|string',
                'email' => 'required|email',
                'filiere' => 'required|string',
                'sujet' => 'nullable|string',
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

    private function findHeaderRowIndex(array $rows, array $requiredColumns): ?int
    {
        foreach ($rows as $index => $row) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $columns = $this->buildColumnMap($row);
            $found = 0;

            foreach ($requiredColumns as $aliases) {
                if ($this->findColumnIndex($columns, $aliases) !== null) {
                    $found++;
                }
            }

            if ($found >= 4) {
                return $index;
            }
        }

        return null;
    }

    private function buildColumnMap(array $headerRow): array
    {
        $columns = [];

        foreach ($headerRow as $index => $value) {
            $label = $this->normalizeLabel((string) $value);

            if ($label !== '') {
                $columns[$label] = $index;
            }
        }

        return $columns;
    }

    private function findColumnIndex(array $columns, array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            $normalizedAlias = $this->normalizeLabel($alias);

            if (array_key_exists($normalizedAlias, $columns)) {
                return $columns[$normalizedAlias];
            }
        }

        return null;
    }

    private function getCellByAliases(array $row, array $columns, array $aliases): string
    {
        $index = $this->findColumnIndex($columns, $aliases);

        if ($index === null) {
            return '';
        }

        return trim((string) ($row[$index] ?? ''));
    }

    private function missingRequiredColumns(array $columns, array $requiredColumns): array
    {
        $missing = [];

        foreach ($requiredColumns as $field => $aliases) {
            if ($this->findColumnIndex($columns, $aliases) === null) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
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
