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
            'students_file' => [
                'required',
                'file',
                'mimes:xlsx,xls'
            ],

            'professeurs_file' => [
                'required',
                'file',
                'mimes:xlsx,xls'
            ],

            'date_soutenance' => [
                'required',
                'date',
                'after:today'
            ],

            'salles' => [
                'required',
                'array'
            ],

            'salles.*' => [
                'string'
            ]
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [

            'students_file.required'
                => 'Le fichier des étudiants est obligatoire.',

            'students_file.file'
                => 'Le fichier des étudiants doit être un fichier valide.',

            'students_file.mimes'
                => 'Le fichier des étudiants doit être Excel (.xlsx ou .xls).',

            'professeurs_file.required'
                => 'Le fichier des professeurs est obligatoire.',

            'professeurs_file.file'
                => 'Le fichier des professeurs doit être un fichier valide.',

            'professeurs_file.mimes'
                => 'Le fichier des professeurs doit être Excel (.xlsx ou .xls).',

            'date_soutenance.required'
                => 'La date des soutenances est obligatoire.',

            'date_soutenance.date'
                => 'Veuillez choisir une date valide.',

            'date_soutenance.after'
                => 'La date de soutenance doit être après aujourd\'hui.',   

            'salles.required'
                => 'Veuillez sélectionner au moins une salle.',

            'salles.array'
                => 'Le format des salles est invalide.',

            'salles.*.string'
                => 'Une des salles sélectionnées est invalide.'
        ];    
    }
}
