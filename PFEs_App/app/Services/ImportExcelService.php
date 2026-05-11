<?php

namespace App\Services;

use App\Models\Etudiant;
use App\Models\Professeur;
use App\Models\Pfe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Http\UploadedFile; // Indispensable pour la synchro avec le contrôleur

class ImportExcelService
{
    /**
     * Lit le fichier à partir de l'objet UploadedFile envoyé par le contrôleur
     */
    private function readExcel(UploadedFile $file): array
    {
        // On récupère le chemin temporaire du fichier envoyé par la requête
        $spreadsheet = IOFactory::load($file->getRealPath());
        return $spreadsheet->getActiveSheet()->toArray();
    }

    /**
     * Valide les données d'une ligne selon le MLD
     */
    public function validateRow(array $data, array $rules)
    {
        return Validator::make($data, $rules);
    }

    
    /**
 * Importation des Étudiants + création automatique des PFEs
 */
public function importEtudiants(UploadedFile $file): array
{
    $rows = $this->readExcel($file);

    $studentsImported = 0;
    $pfesImported = 0;
    $errors = [];

    foreach ($rows as $index => $row) {
        if ($index === 0) continue; // Sauter l'entête

        $data = [
            'nom'     => trim($row[0] ?? ''),
            'prenom'  => trim($row[1] ?? ''),
            'cne'     => trim($row[2] ?? ''),
            'email'   => trim($row[3] ?? ''),
            'filiere' => trim($row[4] ?? ''),
            'sujet'   => trim($row[5] ?? ''),
            'langue'  => trim($row[6] ?? ''),
        ];

        $validator = $this->validateRow($data, [
            'nom'     => 'required|string',
            'prenom'  => 'required|string',
            'cne'     => 'required|string|unique:etudiants,cne',
            'email'   => 'required|email|unique:etudiants,email',
            'filiere' => 'required|string',
            'sujet'   => 'required|string',
            'langue'  => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors[] = "Ligne " . ($index + 1) . " : " . implode(", ", $validator->errors()->all());
            continue;
        }

        try {
            DB::transaction(function () use ($data, &$studentsImported, &$pfesImported) {
                $etudiant = Etudiant::create([
                    'nom'     => $data['nom'],
                    'prenom'  => $data['prenom'],
                    'cne'     => $data['cne'],
                    'email'   => $data['email'],
                    'filiere' => $data['filiere'],
                ]);

                $this->importPfes($data, $etudiant);

                $studentsImported++;
                $pfesImported++;
            });
        } catch (\Exception $e) {
            $errors[] = "Ligne " . ($index + 1) . " : erreur lors de l'importation.";
        }
    }

    return [
        'students_imported' => $studentsImported,
        'pfes_imported' => $pfesImported,
        'errors' => $errors,
    ];
}

    /**
     * Importation des Professeurs
     */
    public function importProfesseurs(UploadedFile $file): array
{
    $rows = $this->readExcel($file);
    $importedCount = 0;
    $errors = [];

    foreach ($rows as $index => $row) {
        if ($index === 0) continue;

        $data = [
            'nom'        => trim($row[0] ?? null),
            'prenom'     => trim($row[1] ?? null),
            'specialite' => trim($row[2] ?? null),
        ];

        // 1. Validation de base (champs requis)
        $validator = $this->validateRow($data, [
            'nom'        => 'required|string',
            'prenom'     => 'required|string',
            'specialite' => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors[] = "Ligne " . ($index + 1) . " : " . implode(", ", $validator->errors()->all());
            continue;
        }

        // 2. IMPROVEMENT: Gestion des doublons
        // On cherche si un professeur avec le même nom ET prénom existe déjà
        $exists = Professeur::where('nom', $data['nom'])
                            ->where('prenom', $data['prenom'])
                            ->exists();

        if ($exists) {
            $errors[] = "Ligne " . ($index + 1) . " : Le professeur " . $data['nom'] . " " . $data['prenom'] . " existe déjà dans la base.";
            continue;
        }

        // 3. Insertion si tout est correct
        Professeur::create($data);
        $importedCount++;
    }

    return ['imported' => $importedCount, 'errors' => $errors];
}

    
    /**
 * Création d'un PFE pour un étudiant
 */
private function importPfes(array $data, Etudiant $etudiant): void
{
    Pfe::create([
        'sujet'        => $data['sujet'],
        'langue'       => $data['langue'],
        'id_etudiant'  => $etudiant->id_etudiant,
        'id_encadrant' => null,
    ]);
}
}