<?php

namespace App\Http\Controllers;

use App\Models\Soutenance;
use App\Services\AffectationService;

class AffectationController extends Controller
{

    public function generate(AffectationService $affectationService)
    {
        session()->forget(['planning_generated', 'verification_completed']);
        Soutenance::query()->delete();

        $result = $affectationService->affecterEncadrants();

        return view('affectations.result', [
            'result' => $result,
        ]);
    }
}
