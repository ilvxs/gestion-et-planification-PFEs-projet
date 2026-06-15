<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportExcelRequest;
use App\Services\ImportExcelService;
use App\Services\PeriodeSoutenanceService;

class ImportController extends Controller
{
    protected $importService;

    public function __construct(ImportExcelService $importService)
    {
        $this->importService = $importService;
    }

    public function index()
    {
        return view('imports.index');
    }

    public function importAll(ImportExcelRequest $request, PeriodeSoutenanceService $periodeService)
    {
        $creneaux = $request->creneaux();
        $analyse = $this->importService->analyserWorkbook($request->file('import_file'));

        if (!empty($analyse['errors'])) {
            return view('imports.result', [
                'title' => 'Resultat Importation',
                'result' => [
                    'students_imported' => 0,
                    'pfes_imported' => 0,
                    'professeurs_imported' => 0,
                    'salles_imported' => 0,
                    'errors' => $analyse['errors'],
                ],
            ]);
        }

        $validationPeriode = $periodeService->valider(
            $request->date_soutenance,
            $request->date_fin_soutenance,
            $analyse['salles_disponibles'],
            $creneaux,
            (int) $analyse['pfes_count']
        );

        if (!$validationPeriode['valid']) {
            return back()
                ->withErrors(['date_fin_soutenance' => $validationPeriode['message']])
                ->withInput($request->except(['import_file']));
        }

        session()->forget(['planning_generated', 'verification_completed']);

        session([
            'date_soutenance' => $request->date_soutenance,
            'date_fin_soutenance' => $request->date_fin_soutenance,
            'creneaux' => $creneaux,
        ]);

        $result = $this->importService->importWorkbook(
            $request->file('import_file')
        );

        return view('imports.result', [
            'title' => 'Resultat Importation',
            'result' => $result,
        ]);
    }
}
