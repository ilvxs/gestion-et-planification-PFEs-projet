<?php

namespace Tests\Unit;

use App\Services\ImportExcelService;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportExcelServiceTest extends TestCase
{
    public function test_workbook_requires_expected_sheet_names(): void
    {
        $path = $this->writeWorkbook([
            'Transformation Digitale & Intel' => [
                ['CNE', 'NOM', 'PRENOM', 'EMAIL PERSONNEL', 'EMAIL ACADEMIQUE', 'FILIERE', 'SUJET', 'LANGUE'],
                ['M123', 'Nom', 'Prenom', 'personnel@example.com', 'academique@example.com', 'tdia', 'Sujet', 'fr'],
            ],
            'Feuil1' => [
                ['Nom', 'Prenom', 'Specialite'],
                ['Prof', 'Un', 'Informatique'],
            ],
            'Feuil2' => [
                ['Salles'],
                ['Salle A'],
            ],
        ]);

        try {
            $file = new UploadedFile(
                $path,
                'bad_sheet_names.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            );

            $result = app(ImportExcelService::class)->analyserWorkbook($file);

            $this->assertSame(0, $result['pfes_count']);
            $this->assertContains(
                'Le fichier Excel doit contenir trois feuilles nommees : professeurs, etudiants, salles.',
                $result['errors']
            );
            $this->assertContains('La feuille professeurs est introuvable.', $result['errors']);
            $this->assertContains('La feuille etudiants est introuvable.', $result['errors']);
            $this->assertContains('La feuille salles est introuvable.', $result['errors']);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function writeWorkbook(array $sheets): string
    {
        $spreadsheet = new Spreadsheet();
        $index = 0;

        foreach ($sheets as $name => $rows) {
            $sheet = $index === 0
                ? $spreadsheet->getActiveSheet()
                : $spreadsheet->createSheet();

            $sheet->setTitle($name);
            $sheet->fromArray($rows, null, 'A1');
            $index++;
        }

        $dir = base_path('storage/framework/testing');

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'import_test_' . uniqid('', true) . '.xlsx';

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
