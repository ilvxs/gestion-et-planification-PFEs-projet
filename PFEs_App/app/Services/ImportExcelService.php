<?php

namespace App\Services;

use App\Models\Etudiant;
use App\Models\Pfe;
use App\Models\Professeur;
use App\Models\Soutenance;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportExcelService
{
    private function readExcel(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());

        return $spreadsheet->getActiveSheet()->toArray();
    }

    public function validateRow(array $data, array $rules)
    {
        return Validator::make($data, $rules);
    }

    public function analyserEtudiants(UploadedFile $file): array
    {
        $preparation = $this->preparerEtudiants($file);

        return [
            'pfes_count' => count($preparation['data']),
            'errors' => $preparation['errors'],
        ];
    }

    public function importEtudiants(UploadedFile $file): array
    {
        $preparation = $this->preparerEtudiants($file);
        $errors = $preparation['errors'];
        $dataToImport = $preparation['data'];

        if (count($errors) > 0) {
            return [
                'students_imported' => 0,
                'pfes_imported' => 0,
                'errors' => $errors,
            ];
        }

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            Soutenance::truncate();
            Pfe::truncate();
            Etudiant::truncate();

            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            return DB::transaction(function () use ($dataToImport) {
                foreach ($dataToImport as $item) {
                    $etudiant = Etudiant::create([
                        'nom' => $item['nom'],
                        'prenom' => $item['prenom'],
                        'cne' => $item['cne'],
                        'email' => $item['email'],
                        'filiere' => $item['filiere'],
                    ]);

                    $this->importPfes($item, $etudiant);
                }

                return [
                    'students_imported' => count($dataToImport),
                    'pfes_imported' => count($dataToImport),
                    'errors' => [],
                ];
            });
        } catch (\Exception $e) {
            return [
                'students_imported' => 0,
                'pfes_imported' => 0,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    public function importProfesseurs(UploadedFile $file): array
    {
        $rows = $this->readExcel($file);

        $errors = [];
        $dataToImport = [];
        $seenProfessors = [];

        foreach ($rows as $index => $row) {
            if ($index === 0 || $index === 1) continue;
            if (empty(array_filter($row))) continue;

            $lineNum = $index + 1;

            $nom = trim($row[0] ?? '');
            $prenom = trim($row[1] ?? '');
            $specialite = trim($row[2] ?? '');
            $profKey = strtoupper($nom . '|' . $prenom);

            $data = [
                'nom' => $nom,
                'prenom' => $prenom,
                'specialite' => $specialite,
            ];

            $validator = $this->validateRow($data, [
                'nom' => 'required|string',
                'prenom' => 'required|string',
                'specialite' => 'required|string',
            ]);

            if ($validator->fails()) {
                $missingFields = implode(', ', $validator->errors()->keys());
                $errors[] = "Ligne $lineNum : Donnees manquantes ou invalides ($missingFields)";
                continue;
            }

            if (in_array($profKey, $seenProfessors)) {
                $errors[] = "Ligne $lineNum : Le professeur $nom $prenom apparait deux fois dans le fichier.";
                continue;
            }

            $seenProfessors[] = $profKey;
            $dataToImport[] = $data;
        }

        if (count($errors) > 0) {
            return [
                'imported' => 0,
                'errors' => $errors,
            ];
        }

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            Soutenance::truncate();
            Professeur::truncate();

            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            return DB::transaction(function () use ($dataToImport) {
                $importedCount = 0;

                foreach ($dataToImport as $data) {
                    Professeur::create($data);
                    $importedCount++;
                }

                return [
                    'imported' => $importedCount,
                    'errors' => [],
                ];
            });
        } catch (\Exception $e) {
            return [
                'imported' => 0,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    private function preparerEtudiants(UploadedFile $file): array
    {
        $rows = $this->readExcel($file);
        $errors = [];
        $dataToImport = [];
        $seenCNEs = [];

        foreach ($rows as $index => $row) {
            if ($index === 0) continue;
            if (empty(array_filter($row))) continue;

            $lineNum = $index + 1;

            $data = [
                'cne' => trim($row[0] ?? ''),
                'nom' => trim($row[1] ?? ''),
                'prenom' => trim($row[2] ?? ''),
                'email' => trim($row[4] ?? ''),
                'filiere' => trim($row[5] ?? ''),
                'sujet' => trim($row[6] ?? ''),
                'langue' => trim($row[7] ?? ''),
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
                $errors[] = "Ligne $lineNum : Donnees manquantes ou invalides ($missingFields)";
                continue;
            }

            if (!empty($data['cne'])) {
                if (in_array($data['cne'], $seenCNEs)) {
                    $errors[] = "Ligne $lineNum : Le CNE " . $data['cne'] . " est duplique dans le fichier.";
                    continue;
                }

                $seenCNEs[] = $data['cne'];
            }

            $dataToImport[] = $data;
        }

        return [
            'data' => $dataToImport,
            'errors' => $errors,
        ];
    }

    private function importPfes(array $data, Etudiant $etudiant): void
    {
        Pfe::create([
            'sujet' => $data['sujet'],
            'langue' => $data['langue'],
            'id_etudiant' => $etudiant->id_etudiant,
            'id_encadrant' => null,
        ]);
    }
}
