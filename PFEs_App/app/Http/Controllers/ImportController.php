<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportExcelRequest;
use App\Services\ImportExcelService;

class ImportController extends Controller
{
    protected $importService;

    public function __construct(ImportExcelService $importService){
        // $this->importService = new ImportExcelService();
        $this->importService = $importService;
    }

    /**
     * Display import page
     */
    public function index(){
        return view('imports.index');
    }

    /**
     * Importer tous le inputs
     */
    public function importAll(ImportExcelRequest $request){

        session([
            'date_soutenance' => $request->date_soutenance,
            'salles' => $request->salles
        ]);

        $studentsResult = $this->importService
            ->importEtudiants(
                $request->file('students_file')
            );

        $professeursResult = $this->importService
            ->importProfesseurs(
                $request->file('professeurs_file')
            );

        $result = [
            'students_imported'
                => $studentsResult['students_imported'],
            'pfes_imported'
                => $studentsResult['pfes_imported'],
            'professeurs_imported'
                => $professeursResult['imported'],
            'errors'
                => array_merge(
                    $studentsResult['errors'],
                    $professeursResult['errors']
                )
        ];

        return view('imports.result', [

            'title' => 'Résultat Importation',

            'result' => $result
        ]);
    }
}
