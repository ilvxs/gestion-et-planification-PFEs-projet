<?php

namespace App\Http\Controllers;

use App\Models\Soutenance;

class ExportController extends Controller
{
    public function index()
    {
        $totalSoutenances = Soutenance::count();
        $planningGenerated = $totalSoutenances > 0;
        $verificationCompleted = session('verification_completed', false);

        return view('export.index', compact(
            'totalSoutenances',
            'planningGenerated',
            'verificationCompleted'
        ));
    }
}
