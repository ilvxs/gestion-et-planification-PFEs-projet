<?php

namespace App\Http\Controllers;

use App\Services\VerificationService;

class VerificationController extends Controller
{
    public function index(VerificationService $verificationService)
    {
        $affectation = $verificationService->verifierAffectation();
        $planning = $verificationService->verifierPlanning();

        $isValid = ($affectation['is_valid'] ?? false)
            && ($planning['is_valid'] ?? false);

        if ($isValid) {
            session([
                'verification_completed' => true,
            ]);
        } else {
            session()->forget('verification_completed');
        }

        return view('verification.result', [
            'affectation' => $affectation,
            'planning' => $planning,
            'isValid' => $isValid,
        ]);
    }

    public function continuerVersDocuments(VerificationService $verificationService)
    {
        $affectation = $verificationService->verifierAffectation();
        $planning = $verificationService->verifierPlanning();

        $isValid = ($affectation['is_valid'] ?? false)
            && ($planning['is_valid'] ?? false);

        if (!$isValid) {
            session()->forget('verification_completed');

            return redirect()
                ->route('verification.index')
                ->with('error', 'Impossible de continuer : la vérification complète a échoué.');
        }

        session([
            'verification_completed' => true,
        ]);

        return redirect()
            ->route('documents.index')
            ->with('success', 'Vérification terminée avec succès.');
    }
}
