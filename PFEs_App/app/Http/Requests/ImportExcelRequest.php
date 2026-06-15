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

            'date_fin_soutenance' => [
                'required',
                'date',
                'after_or_equal:date_soutenance'
            ],

            'salles' => [
                'required',
                'array'
            ],

            'salles.*' => [
                'string'
            ],

            'heure_debut_matin' => [
                'required',
                'date_format:H:i'
            ],

            'heure_fin_matin' => [
                'required',
                'date_format:H:i'
            ],

            'heure_debut_apres_midi' => [
                'required',
                'date_format:H:i'
            ],

            'heure_fin_apres_midi' => [
                'required',
                'date_format:H:i'
            ]
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $duree = (int) config('pfe.duree_soutenance_minutes', 60);

            if ($duree <= 0) {
                $validator->errors()->add(
                    'heure_debut_matin',
                    'La duree de soutenance configuree doit etre superieure a 0 minute.'
                );

                return;
            }

            $this->validerPlageHoraire(
                $validator,
                'matin',
                'heure_debut_matin',
                'heure_fin_matin',
                $duree
            );

            $this->validerPlageHoraire(
                $validator,
                'apres-midi',
                'heure_debut_apres_midi',
                'heure_fin_apres_midi',
                $duree
            );
        });
    }

    public function creneaux(): array
    {
        $duree = max(1, (int) config('pfe.duree_soutenance_minutes', 60));

        $creneaux = array_merge(
            $this->genererCreneauxPourPlage(
                $this->input('heure_debut_matin'),
                $this->input('heure_fin_matin'),
                $duree
            ),
            $this->genererCreneauxPourPlage(
                $this->input('heure_debut_apres_midi'),
                $this->input('heure_fin_apres_midi'),
                $duree
            )
        );

        $creneaux = array_values(array_unique($creneaux));

        usort($creneaux, function ($a, $b) {
            return $this->minutesDepuisMinuit($a) <=> $this->minutesDepuisMinuit($b);
        });

        return $creneaux;
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

            'date_fin_soutenance.required'
                => 'La date de fin des soutenances est obligatoire.',

            'date_fin_soutenance.date'
                => 'Veuillez choisir une date de fin valide.',

            'date_fin_soutenance.after_or_equal'
                => 'La date de fin des soutenances doit etre apres ou egale a la date de debut.',

            'salles.required'
                => 'Veuillez sélectionner au moins une salle.',

            'salles.array'
                => 'Le format des salles est invalide.',

            'salles.*.string'
                => 'Une des salles sélectionnées est invalide.',

            'heure_debut_matin.required'
                => 'Veuillez saisir l\'heure de debut du matin.',

            'heure_debut_matin.date_format'
                => 'L\'heure de debut du matin est invalide.',

            'heure_fin_matin.required'
                => 'Veuillez saisir l\'heure de fin du matin.',

            'heure_fin_matin.date_format'
                => 'L\'heure de fin du matin est invalide.',

            'heure_debut_apres_midi.required'
                => 'Veuillez saisir l\'heure de debut de l\'apres-midi.',

            'heure_debut_apres_midi.date_format'
                => 'L\'heure de debut de l\'apres-midi est invalide.',

            'heure_fin_apres_midi.required'
                => 'Veuillez saisir l\'heure de fin de l\'apres-midi.',

            'heure_fin_apres_midi.date_format'
                => 'L\'heure de fin de l\'apres-midi est invalide.'
        ];    
    }

    private function validerPlageHoraire(
        $validator,
        string $periode,
        string $champDebut,
        string $champFin,
        int $duree
    ): void {
        if (
            $validator->errors()->has($champDebut) ||
            $validator->errors()->has($champFin)
        ) {
            return;
        }

        $debut = $this->minutesDepuisMinuit($this->input($champDebut));
        $fin = $this->minutesDepuisMinuit($this->input($champFin));

        if ($debut === null || $fin === null) {
            return;
        }

        if ($fin <= $debut) {
            $validator->errors()->add(
                $champFin,
                "L'heure de fin du {$periode} doit etre apres l'heure de debut."
            );

            return;
        }

        if (($fin - $debut) < $duree) {
            $validator->errors()->add(
                $champFin,
                "La plage du {$periode} doit contenir au moins un creneau de {$duree} minutes."
            );
        }
    }

    private function genererCreneauxPourPlage($heureDebut, $heureFin, int $duree): array
    {
        $debut = $this->minutesDepuisMinuit($heureDebut);
        $fin = $this->minutesDepuisMinuit($heureFin);

        if ($debut === null || $fin === null || $fin <= $debut) {
            return [];
        }

        $creneaux = [];

        for ($minute = $debut; ($minute + $duree) <= $fin; $minute += $duree) {
            $creneaux[] = $this->formaterHeure($minute);
        }

        return $creneaux;
    }

    private function minutesDepuisMinuit($heure): ?int
    {
        if (!is_string($heure) || !preg_match('/^(\d{2}):(\d{2})$/', $heure, $matches)) {
            return null;
        }

        $h = (int) $matches[1];
        $m = (int) $matches[2];

        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            return null;
        }

        return ($h * 60) + $m;
    }

    private function formaterHeure(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return sprintf('%02d:%02d', $h, $m);
    }
}
