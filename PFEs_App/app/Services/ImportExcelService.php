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
    /**
     * Importation des Étudiants + RÉINITIALISATION + DOUBLONS FICHIER
     */
    public function importEtudiants(UploadedFile $file): array
    {
        $rows = $this->readExcel($file);
        $errors = [];
        $dataToImport = []; // Stockage temporaire des lignes valides
        $seenCNEs = [];

        // --- ÉTAPE 1 : VALIDATION DE TOUT LE FICHIER ---
        foreach ($rows as $index => $row) {
            if ($index === 0) continue; 
            if (empty(array_filter($row))) continue;

            $lineNum = $index + 1;
            $data = [
                'cne'     => trim($row[0] ?? ''),
                'nom'     => trim($row[1] ?? ''),
                'prenom'  => trim($row[2] ?? ''),
                'email'   => trim($row[4] ?? ''),
                'filiere' => trim($row[5] ?? ''),
                'sujet'   => trim($row[6] ?? ''),
                'langue'  => trim($row[7] ?? ''),
            ];

            // Validation des champs requis
            $validator = $this->validateRow($data, [
                'cne'     => 'required|string|unique:etudiants,cne',
                'nom'     => 'required|string',
                'prenom'  => 'required|string',
                'email'   => 'required|email',
                'filiere' => 'required|string',
                'sujet'   => 'required|string',
                'langue'  => 'required|string',
            ]);

            if ($validator->fails()) {
                $missingFields = implode(', ', $validator->errors()->keys());
                $errors[] = "Ligne $lineNum : Données manquantes ou invalides ($missingFields)";
            }

            // Vérification des doublons CNE dans le fichier
            if (!empty($data['cne'])) {
                if (in_array($data['cne'], $seenCNEs)) {
                    $errors[] = "Ligne $lineNum : Le CNE " . $data['cne'] . " est dupliqué dans le fichier.";
                } else {
                    $seenCNEs[] = $data['cne'];
                }
            }

            // Si aucune erreur pour l'instant, on prépare l'insertion
            $dataToImport[] = $data;
        }

        // --- ÉTAPE 2 : INSERTION OU REFUS ---
        
        // S'il y a la moindre erreur, on arrête tout ici
        if (count($errors) > 0) {
            return [
                'students_imported' => 0,
                'pfes_imported' => 0,
                'errors' => $errors, // On retourne toutes les erreurs sans rien enregistrer
            ];
        }

        // Si aucune erreur, on procède à l'enregistrement massif
        try {
            return DB::transaction(function () use ($dataToImport) {
                // On ne réinitialise la base que maintenant
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                Pfe::truncate();
                Etudiant::truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');

                foreach ($dataToImport as $item) {
                    $etudiant = Etudiant::create([
                        'nom'     => $item['nom'],
                        'prenom'  => $item['prenom'],
                        'cne'     => $item['cne'],
                        'email'   => $item['email'],
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
                'errors' => ["Une erreur technique est survenue lors de l'insertion finale."],
            ];
        }
    }
        /**
         * Importation des Professeurs
         */
    public function importProfesseurs(UploadedFile $file): array
    {
        // 1. Réinitialisation complète de la table
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Professeur::truncate(); 
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $rows = $this->readExcel($file);
        $importedCount = 0;
        $errors = [];
        
        // Tableau pour suivre les doublons à l'intérieur du fichier Excel
        $seenProfessors = [];

        foreach ($rows as $index => $row) {
            if ($index === 0 || $index === 1) continue;

            $nom = trim($row[0] ?? '');
            $prenom = trim($row[1] ?? '');
            $specialite = trim($row[2] ?? '');

            // Création d'une clé unique pour ce professeur (ex: "NOM-PRENOM")
            $profKey = strtoupper($nom . '|' . $prenom);

            $data = [
                'nom'        => $nom,
                'prenom'     => $prenom,
                'specialite' => $specialite,
            ];

            // Validation des champs vides/format
            $validator = $this->validateRow($data, [
                'nom'        => 'required|string',
                'prenom'     => 'required|string',
                'specialite' => 'required|string',
            ]);

            if ($validator->fails()) {
                $errors[] = "Ligne " . ($index + 1) . " : " . implode(", ", $validator->errors()->all());
                continue;
            }

            // 2. VÉRIFICATION DES DOUBLONS DANS LE FICHIER
            if (in_array($profKey, $seenProfessors)) {
                $errors[] = "Ligne " . ($index + 1) . " : Le professeur $nom $prenom apparaît deux fois dans le fichier Excel.";
                continue;
            }

            // Ajouter ce professeur à la liste des "déjà vus"
            $seenProfessors[] = $profKey;

            // 3. Insertion
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