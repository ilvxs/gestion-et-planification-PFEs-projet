<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportExcelRequest;
use App\Models\Soutenance;
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
        $analyseEtudiants = $this->importService->analyserEtudiants($request->file('students_file'));

        if (empty($analyseEtudiants['errors'])) {
            $validationPeriode = $periodeService->valider(
                $request->date_soutenance,
                $request->date_fin_soutenance,
                $request->salles ?? [],
                $creneaux,
                (int) $analyseEtudiants['pfes_count']
            );

            if (!$validationPeriode['valid']) {
                return back()
                    ->withErrors(['date_fin_soutenance' => $validationPeriode['message']])
                    ->withInput($request->except(['students_file', 'professeurs_file']));
            }
        }

        session()->forget(['planning_generated', 'verification_completed']);
        Soutenance::query()->delete();

        session([
            'date_soutenance' => $request->date_soutenance,
            'date_fin_soutenance' => $request->date_fin_soutenance,
            'salles' => $request->salles,
            'creneaux' => $creneaux,
        ]);

        $studentsResult = $this->importService->importEtudiants(
            $request->file('students_file')
        );

        $professeursResult = $this->importService->importProfesseurs(
            $request->file('professeurs_file')
        );

        $result = [
            'students_imported' => $studentsResult['students_imported'],
            'pfes_imported' => $studentsResult['pfes_imported'],
            'professeurs_imported' => $professeursResult['imported'],
            'errors' => array_merge(
                $studentsResult['errors'],
                $professeursResult['errors']
            ),
        ];

        return view('imports.result', [
            'title' => 'Résultat Importation',
            'result' => $result,
        ]);
    }
}
