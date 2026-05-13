<?php

namespace App\Services;

use App\Models\Pfe;
use App\Models\Professeur;
use Illuminate\Support\Facades\DB;

class AffectationService
{
    public function affecterEncadrants(): array
    {
        $pfesSansEncadrant = Pfe::whereNull('id_encadrant')->get();
        $professeurs = Professeur::all();

        if ($pfesSansEncadrant->isEmpty()) {
            return [
                'affected' => 0,
                'errors' => ['Aucun PFE sans encadrant trouvé.'],
                'warnings' => [],
                'repartition' => [],
            ];
        }

        if ($professeurs->isEmpty()) {
            return [
                'affected' => 0,
                'errors' => ['Aucun professeur trouvé.'],
                'warnings' => [],
                'repartition' => [],
            ];
        }

        return DB::transaction(function () use ($pfesSansEncadrant, $professeurs) {
            $affected = 0;

            // Charge actuelle de chaque professeur
            $charges = [];

            foreach ($professeurs as $professeur) {
                $charges[$professeur->id_professeur] = Pfe::where(
                    'id_encadrant',
                    $professeur->id_professeur
                )->count();
            }

            // Affecter chaque PFE au professeur le moins chargé
            foreach ($pfesSansEncadrant as $pfe) {
                $professeur = $this->choisirProfesseurMoinsCharge($professeurs, $charges);

                $pfe->update([
                    'id_encadrant' => $professeur->id_professeur,
                ]);

                $charges[$professeur->id_professeur]++;
                $affected++;
            }

            return [
                'affected' => $affected,
                'errors' => [],
                'warnings' => [],
                'repartition' => $this->getRepartition(),
            ];
        });
    }

    /**
     * Choisir le professeur qui a le moins de PFEs actuellement
     */
    private function choisirProfesseurMoinsCharge($professeurs, array $charges): Professeur
    {
        return $professeurs->sortBy(function ($professeur) use ($charges) {
            return $charges[$professeur->id_professeur];
        })->first();
    }

    /**
     * Retourne la répartition finale par professeur
     */
    private function getRepartition(): array
    {
        return Professeur::all()->map(function ($professeur) {
            return [
                'nom' => $professeur->nom . ' ' . $professeur->prenom,
                'specialite' => $professeur->specialite,
                'total' => Pfe::where('id_encadrant', $professeur->id_professeur)->count(),
            ];
        })->toArray();
    }
}