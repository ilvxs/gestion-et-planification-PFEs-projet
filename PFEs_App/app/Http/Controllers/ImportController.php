<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportExcelRequest;
use App\Services\ImportExcelService;

class ImportController extends Controller
{
    protected $importService;

    public function __construct(ImportExcelService $importService)
    {
        // $this->importService = new ImportExcelService();
        $this->importService = $importService;
    }

    /**
     * Display import page
     */
    public function index()
    {
        return view('imports.index');
    }

    /**
     * Import students Excel file
     */
    public function importEtudiants(ImportExcelRequest $request)
    {
        $file = $request->file('excel_file');
        $result = $this->importService->importEtudiants($file);
        return view('imports.result', [
            'result' => $result
        ]);
    }

    /**
     * Import professors Excel file
     */
    public function importProfesseurs(ImportExcelRequest $request)
    {
        $file = $request->file('excel_file');

        $result = $this->importService->importProfesseurs($file);

        return view('imports.result', [
            'result' => $result
        ]);
    }

    /**
     * Import PFEs Excel file
     */
    public function importPfes(ImportExcelRequest $request)
    {
        $file = $request->file('excel_file');

        $result = $this->importService->importPfes($file);

        return view('imports.result', [
            'result' => $result
        ]);
    }
}
