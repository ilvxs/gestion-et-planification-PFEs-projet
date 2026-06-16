<?php

namespace App\Services;

use App\Models\Pfe;
use App\Models\Professeur;
use Illuminate\Support\Facades\DB;

class AffectationService
{
    public function affecterEncadrants(): array
    {
        $pfesSansEncadrant = Pfe::all();
        $professeurs = Professeur::all();

        if ($pfesSansEncadrant->isEmpty()) {
            return [
                'affected' => 0,
                'errors' => ['Aucun PFE trouvé.'],
                'warnings' => [],
                'repartition' => $this->getRepartition(),
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
            $warnings = [];

            // Nbre total des PFEs dans la base
            $totalPfes = Pfe::count();

            // Nbre total des profs
            $totalProfesseurs = $professeurs->count();

            // Charge equitable approximative
            $chargeMax = (int) ceil($totalPfes / $totalProfesseurs);

            // Charge actuelle de chaque prof
            $charges = [];

            foreach ($professeurs as $professeur) {
                $charges[$professeur->id_professeur] = Pfe::where(
                    'id_encadrant',
                    $professeur->id_professeur
                    )->count();
            }

            // Separer les PFEs ang et les autres
            $pfesAnglais = $pfesSansEncadrant->filter(function ($pfe) {
                return $this->isPfeAnglais($pfe);
            });

            $pfesNonAnglais = $pfesSansEncadrant->reject(function ($pfe) {
                return $this->isPfeAnglais($pfe);
            });

            // 1. Affecter les PFEs ang
            foreach ($pfesAnglais as $pfe) {
                $professeur = $this->choisirProfesseurPourPfeAnglais(
                    $professeurs,
                    $charges,
                    $chargeMax
                );

                if (!$this->isProfAnglais($professeur)) {
                    $libellePfe = $pfe->sujet ?: $pfe->id_pfe;
                    $warnings[] = "Le PFE {$libellePfe} est en anglais, mais il a été affecté à un professeur non spécialisé en anglais.";
                }

                $pfe->update([
                    'id_encadrant' => $professeur->id_professeur,
                ]);

                $charges[$professeur->id_professeur]++;
                $affected++;
            }

            // 2. Affecter les autres PFEs
            foreach ($pfesNonAnglais as $pfe) {
                $professeur = $this->choisirProfesseurMoinsCharge(
                    $professeurs,
                    $charges
                );

                $pfe->update([
                    'id_encadrant' => $professeur->id_professeur,
                ]);

                $charges[$professeur->id_professeur]++;
                $affected++;
            }

            return [
                'affected' => $affected,
                'errors' => [],
                'warnings' => $warnings,
                'repartition' => $this->getRepartition(),
            ];
        });
    }

    // Verification si un PFE est en ang
    
    private function isPfeAnglais(Pfe $pfe): bool
    {
        $langue = strtolower(trim($pfe->langue));

        return in_array($langue, [
            'anglais',
            'english',
            'eng',
            'en',
        ]);
    }

    // verification si un prof est spécialisé en ang
    
    private function isProfAnglais(Professeur $professeur): bool
    {
        $specialite = strtolower(trim($professeur->specialite));

        return str_contains($specialite, 'anglais')
            || str_contains($specialite, 'english');
    }

    // Choisir un prof pour un PFE en ang
    
    private function choisirProfesseurPourPfeAnglais($professeurs, array $charges, int $chargeMax): Professeur
    {
        $profsAnglais = $professeurs->filter(function ($professeur) {
            return $this->isProfAnglais($professeur);
        });

        // Si on a des profs ang
        if ($profsAnglais->isNotEmpty()) {
            //choisir un prof ang qui n'a pas dépassé la charge max
            $profsAnglaisDisponibles = $profsAnglais->filter(function ($professeur) use ($charges, $chargeMax) {
                return $charges[$professeur->id_professeur] < $chargeMax;
            });
            //prof quelconque
            if ($profsAnglaisDisponibles->isNotEmpty()) {
                return $this->choisirProfesseurMoinsCharge($profsAnglaisDisponibles, $charges);
            }
        }

        // Si les profs ang sont absents ou saturés
        return $this->choisirProfesseurMoinsCharge($professeurs, $charges);
    }

    //Choisir le prof qui a le moins de PFEs

    private function choisirProfesseurMoinsCharge($professeurs, array $charges): Professeur
    {
        $professeurs = $professeurs->sortBy(function ($professeur) use ($charges) {
            return $charges[$professeur->id_professeur];
        });
        
        return $professeurs->first();
    }

    //Retourne la répartition finale par prof

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