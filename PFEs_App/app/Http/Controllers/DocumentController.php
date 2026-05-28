<?php

namespace App\Http\Controllers;

use App\Models\Pfe;
use App\Models\Soutenance;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpWord\TemplateProcessor;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use ZipArchive;

class DocumentController extends Controller
{
    public function planning()
    {
        $soutenances = Soutenance::with([
                'pfe.etudiant',
                'pfe.encadrant',
                'jury1',
                'jury2',
            ])
            ->orderBy('date_soutenance')
            ->orderBy('heure_debut')
            ->orderBy('salle')
            ->get();

        if ($soutenances->isEmpty()) {
            return redirect()
                ->route('export.index')
                ->with('error', 'Aucun planning trouvé. Veuillez générer le planning avant l’exportation.');
        }

        return Pdf::loadView('documents.planning_pdf', [
                'soutenances' => $soutenances,
                'profColors' => $this->profColors($soutenances),
            ])
            ->setPaper('a4', 'landscape')
            ->download('Planning_des_soutenances_PFE.pdf');
    }

    public function affectation()
    {
        $pfes = Pfe::with(['etudiant', 'encadrant'])
            ->whereNotNull('id_encadrant')
            ->orderBy('id_encadrant')
            ->get();

        if ($pfes->isEmpty()) {
            return redirect()
                ->route('export.index')
                ->with('error', 'Aucune affectation trouvée. Veuillez lancer l’affectation d’abord.');
        }

        $groupes = $pfes->groupBy('id_encadrant');

        return Pdf::loadView('documents.affectation_pdf', [
                'groupes' => $groupes,
            ])
            ->setPaper('a4', 'landscape')
            ->download('Affectation_des_encadrants_PFE.pdf');
    }

    public function pvs()
    {
        $templatePath = storage_path('app/templates/fiche_evaluation_pfe.docx');

        if (!file_exists($templatePath)) {
            return redirect()
                ->route('export.index')
                ->with('error', 'Le modèle Word des PVs est introuvable : storage/app/templates/fiche_evaluation_pfe.docx');
        }

        $soutenances = Soutenance::with([
                'pfe.etudiant',
                'pfe.encadrant',
                'jury1',
                'jury2',
            ])
            ->orderBy('date_soutenance')
            ->orderBy('heure_debut')
            ->get();

        if ($soutenances->isEmpty()) {
            return redirect()
                ->route('export.index')
                ->with('error', 'Aucune soutenance trouvée. Veuillez générer le planning avant les PVs.');
        }

        $tempBase = storage_path('app/temp_pvs');

        if (!File::exists($tempBase)) {
            File::makeDirectory($tempBase, 0777, true);
        }

        $workDir = $tempBase . DIRECTORY_SEPARATOR . 'work_' . uniqid();
        $mainDir = $workDir . DIRECTORY_SEPARATOR . 'PVs';

        File::makeDirectory($mainDir, 0777, true);

        foreach ($soutenances as $soutenance) {
            $pfe = $soutenance->pfe;
            $etudiant = $pfe?->etudiant;
            $encadrant = $pfe?->encadrant;

            if (!$pfe || !$etudiant || !$encadrant) {
                continue;
            }

            $encadrantFolderName = $this->safeFileName(
                $encadrant->nom . '_' . $encadrant->prenom
            );

            $encadrantDir = $mainDir . DIRECTORY_SEPARATOR . $encadrantFolderName;

            if (!File::exists($encadrantDir)) {
                File::makeDirectory($encadrantDir, 0777, true);
            }

            $studentFileName = $this->safeFileName(
                $etudiant->nom . '_' . $etudiant->prenom
            );

            $docxPath = $encadrantDir . DIRECTORY_SEPARATOR . $studentFileName . '.docx';

            $counter = 2;

            while (file_exists($docxPath)) {
                $docxPath = $encadrantDir . DIRECTORY_SEPARATOR . $studentFileName . '_' . $counter . '.docx';
                $counter++;
            }

            $template = new TemplateProcessor($templatePath);

            $template->setValue('annee_universitaire', config('pfe.annee_universitaire'));

            $template->setValue(
                'nom_prenom_etudiant',
                $this->nomComplet($etudiant)
            );

            $template->setValue(
                'filiere',
                strtoupper((string) ($etudiant->filiere ?? '-'))
            );

            $template->setValue(
                'intitule_rapport',
                (string) ($pfe->sujet ?? '-')
            );

            $template->setValue(
                'encadrant_interne',
                $this->nomComplet($encadrant)
            );

            /*
            * Dans ton modèle, il y a 3 lignes jury :
            * Président, Rapporteur, Rapporteur.
            *
            * Ici :
            * - jury1 = Président
            * - jury2 = Rapporteur
            * - encadrant = Rapporteur
            */
            $template->setValue(
                'jury_president',
                $this->nomComplet($soutenance->jury1)
            );

            $template->setValue(
                'jury_rapporteur_1',
                $this->nomComplet($soutenance->jury2)
            );

            $template->setValue(
                'jury_rapporteur_2',
                $this->nomComplet($encadrant)
            );

            $template->setValue(
                'date_soutenance',
                $soutenance->date_soutenance
                    ? Carbon::parse($soutenance->date_soutenance)->format('d/m/Y')
                    : ''
            );

            $template->saveAs($docxPath);
        }

        $zipPath = $tempBase . DIRECTORY_SEPARATOR . 'PVs_' . now()->format('Ymd_His') . '.zip';

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            File::deleteDirectory($workDir);

            return redirect()
                ->route('export.index')
                ->with('error', 'Impossible de créer le fichier ZIP des PVs.');
        }

        $this->addFolderToZip($zip, $mainDir, $workDir);

        $zip->close();

        File::deleteDirectory($workDir);

        return response()
            ->download($zipPath, 'PVs.zip')
            ->deleteFileAfterSend(true);
    }

    private function profColors($soutenances): array
    {
        $palette = [
            '#00b050', '#f4b183', '#ff66cc', '#92d050', '#00b0f0',
            '#ffff00', '#7030a0', '#c6e0b4', '#ff0000', '#d9e1f2',
            '#00ff00', '#ffc000', '#cc99ff', '#a9d18e', '#b4c6e7',
            '#f8cbad', '#c00000', '#00b050', '#9e480e', '#99ff99',
            '#ff9999', '#bfbfbf', '#0070c0', '#ff00ff', '#92cddc',
        ];

        $colors = [];

        foreach ($soutenances as $soutenance) {
            $profs = [
                $soutenance->pfe?->encadrant,
                $soutenance->jury1,
                $soutenance->jury2,
            ];

            foreach ($profs as $prof) {
                if (!$prof) {
                    continue;
                }

                $id = (int) $prof->id_professeur;

                if (!isset($colors[$id])) {
                    $colors[$id] = $palette[$id % count($palette)];
                }
            }
        }

        return $colors;
    }
    private function addFolderToZip(ZipArchive $zip, string $folderPath, string $basePath): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folderPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();

            $relativePath = substr($filePath, strlen($basePath) + 1);
            $relativePath = str_replace('\\', '/', $relativePath);

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    private function safeFileName(string $text): string
    {
        $text = trim($text);

        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        if ($converted !== false) {
            $text = $converted;
        }

        $text = preg_replace('/[^A-Za-z0-9_\-]/', '_', $text);
        $text = preg_replace('/_+/', '_', $text);
        $text = trim($text, '_');

        return $text !== '' ? $text : 'document';
    }

    private function nomComplet($person): string
    {
        if (!$person) {
            return '-';
        }

        return trim(($person->nom ?? '') . ' ' . ($person->prenom ?? '')) ?: '-';
    }
}