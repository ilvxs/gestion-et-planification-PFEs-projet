<?php

namespace App\Http\Controllers;

use App\Services\VerificationService;

class VerificationController extends Controller
{
    public function index(VerificationService $verificationService)
    {
        $affectation = $verificationService->verifierAffectation();
        $planning = $verificationService->verifierPlanning();

        //Les alertes ne bloquent pas l’exportation, seules les erreurs bloquent
         
        $isValid = empty($affectation['errors']) && empty($planning['errors']);

        if ($isValid) {
            session(['verification_completed' => true]);
        } else {
            session()->forget('verification_completed');
        }

        return view('verification.result', compact('affectation', 'planning', 'isValid'));
    }
}
