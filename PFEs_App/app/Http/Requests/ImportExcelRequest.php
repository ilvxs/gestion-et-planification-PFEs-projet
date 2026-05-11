<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportExcelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "excel_file" => [
                "required", "file", "mimes:xlsx,xls"
            ],
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'excel_file.required' => 'Veuillez sélectionner un fichier Excel.',
            'excel_file.file' => 'Le fichier envoyé est invalide.',
            'excel_file.mimes' => 'Le fichier doit être au format xlsx ou xls.'
        ];
    }
}
