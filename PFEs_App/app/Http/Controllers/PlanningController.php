<?php

namespace App\Http\Controllers;

use App\Models\Salle;
use App\Services\PlanningService;
use App\Models\Soutenance;

class PlanningController extends Controller
{
    public function generate(PlanningService $planningService)
    {
        $dateDebut = session('date_soutenance');
        $dateFin = session('date_fin_soutenance');
        $creneaux = session('creneaux');
        $salles = Salle::where('disponible', true)
            ->orderBy('nom')
            ->get();

        if (!$dateDebut || !$dateFin || $salles->isEmpty() || empty($creneaux)) {
            return view('planning.result', [
                'result' => [
                    'created' => 0,
                    'errors' => [
                        "La periode de soutenance, les salles disponibles ou les creneaux ne sont pas disponibles. Veuillez refaire l'importation."
                    ],
                    'warnings' => [],
                    'planning' => [],
                    'repartition_profs' => [],
                    'repartition_filieres' => [],
                ],
            ]);
        }

        session()->forget('verification_completed');

        $result = $planningService->generer($dateDebut, $salles, $creneaux, $dateFin);

        if (empty($result['errors']) && (($result['created'] ?? 0) > 0)) {
            session(['planning_generated' => true]);
        } else {
            session()->forget('planning_generated');
        }

        return view('planning.result', [
            'result' => $result,
        ]);
    }

    public function viewer()
    {
        return view('planning.viewer');
    }

    public function planningJson()
    {
        $soutenances = Soutenance::with([
                'salle',
                'pfe.etudiant',
                'pfe.encadrant',
                'jury1',
                'jury2',
            ])
            ->orderBy('date_soutenance')
            ->orderBy('heure_debut')
            ->orderBy('id_salle')
            ->get();

        return response()->json(
            $soutenances->map(function ($soutenance) {
                $pfe = $soutenance->pfe;
                $etudiant = $pfe?->etudiant;
                $encadrant = $pfe?->encadrant;
                $jury1 = $soutenance->jury1;
                $jury2 = $soutenance->jury2;

                $profAnglais =
                    $this->isEnglish($encadrant?->specialite)
                    || $this->isEnglish($jury1?->specialite)
                    || $this->isEnglish($jury2?->specialite);

                $alerteAnglais = $this->isEnglish($pfe?->langue) && !$profAnglais;

                return [
                    'id' => $soutenance->id_soutenance,
                    'date' => (string) $soutenance->date_soutenance,
                    'heure' => substr((string) $soutenance->heure_debut, 0, 5),
                    'salle' => $soutenance->salle?->nom ?? '-',

                    'pfe' => $pfe?->sujet ?: 'PFE ' . ($pfe?->id_pfe ?? '-'),
                    'langue' => $pfe?->langue ?? '-',

                    'etudiant' => $this->nomComplet($etudiant),
                    'filiere' => $etudiant?->filiere ?? '-',

                    'encadrant' => $this->nomComplet($encadrant),
                    'jury1' => $this->nomComplet($jury1),
                    'jury2' => $this->nomComplet($jury2),

                    'alerte_anglais' => $alerteAnglais,
                ];
            })->values()
        );
    }

    private function nomComplet($personne): string
    {
        if (!$personne) {
            return '-';
        }

        $nom = trim((string) ($personne->nom ?? ''));
        $prenom = trim((string) ($personne->prenom ?? ''));

        $fullName = trim($nom . ' ' . $prenom);

        return $fullName !== '' ? $fullName : '-';
    }

    private function isEnglish(?string $value): bool
    {
        $value = strtolower(trim((string) $value));

        return str_contains($value, 'anglais')
            || str_contains($value, 'english')
            || in_array($value, ['eng', 'en'], true);
    }
}
