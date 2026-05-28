<?php

namespace App\Http\Controllers;

use App\Services\PlanningService;

class PlanningController extends Controller
{
    public function generate(PlanningService $planningService)
    {
        $dateDebut = session('date_soutenance');
        $salles = session('salles');
        $creneaux = session('creneaux');

        if (!$dateDebut || empty($salles) || empty($creneaux)) {
            return view('planning.result', [
                'result' => [
                    'created' => 0,
                    'errors' => [
                        "La date de soutenance ou les salles ne sont pas disponibles. Veuillez refaire l'importation."
                    ],
                    'warnings' => [],
                    'planning' => [],
                    'repartition_profs' => [],
                    'repartition_filieres' => [],
                ],
            ]);
        }

        session()->forget('verification_completed');

        $result = $planningService->generer($dateDebut, $salles, $creneaux);

        if (empty($result['errors']) && (($result['created'] ?? 0) > 0)) {
            session(['planning_generated' => true]);
        } else {
            session()->forget('planning_generated');
        }

        return view('planning.result', [
            'result' => $result,
        ]);
    }
}
