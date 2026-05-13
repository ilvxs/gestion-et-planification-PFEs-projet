<?php

namespace App\Http\Controllers;

use App\Services\AffectationService;

class AffectationController extends Controller
{
    public function index()
    {
        return view('affectations.index');
    }

    public function generate(AffectationService $affectationService)
    {
        $result = $affectationService->affecterEncadrants();    
        return view('affectations.result', [
            'result' => $result
         ]);
     }
}