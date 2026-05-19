<?php

namespace App\Http\Controllers;

use App\Services\PlanningService;

class PlanningController extends Controller
{
    public function index()
    {
        return view('planning.index');
    }

    public function generate(PlanningService $planningService)
    {
        $dateDebut = session('date_soutenance');
        $salles = session('salles');

        if (!$dateDebut || empty($salles)) {
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

        /*
         * Si on régénère le planning, l'ancienne vérification complète
         * n'est plus valable.
         */
        session()->forget('verification_completed');

        $result = $planningService->generer($dateDebut, $salles);

        return view('planning.result', [
            'result' => $result,
        ]);
    }
}