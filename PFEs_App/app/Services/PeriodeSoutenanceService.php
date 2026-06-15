<?php

namespace App\Services;

use Carbon\Carbon;

class PeriodeSoutenanceService
{
    public function valider($dateDebut, $dateFin, array $salles, array $creneaux, int $nombreSoutenances): array
    {
        $salles = $this->normaliserListe($salles);
        $creneaux = $this->normaliserListe($creneaux);
        $capaciteParJour = count($salles) * count($creneaux);

        $resultat = [
            'valid' => false,
            'message' => null,
            'capacite_par_jour' => $capaciteParJour,
            'jours_necessaires' => 0,
            'jours_planifiables' => 0,
            'date_fin_attendue' => null,
        ];

        if ($nombreSoutenances <= 0) {
            $resultat['message'] = 'Le fichier des etudiants/PFEs ne contient aucune soutenance a planifier.';

            return $resultat;
        }

        if ($capaciteParJour <= 0) {
            $resultat['message'] = 'Veuillez selectionner au moins une salle et un creneau valide.';

            return $resultat;
        }

        try {
            $debut = Carbon::parse($dateDebut)->startOfDay();
            $fin = Carbon::parse($dateFin)->startOfDay();
        } catch (\Throwable $e) {
            $resultat['message'] = 'La periode des soutenances est invalide.';

            return $resultat;
        }

        if ($fin->lt($debut)) {
            $resultat['message'] = 'La date de fin des soutenances doit etre apres ou egale a la date de debut.';

            return $resultat;
        }

        $joursNecessaires = (int) ceil($nombreSoutenances / $capaciteParJour);
        $dateFinAttendue = $this->dateFinAttendue($debut, $joursNecessaires);

        $resultat['jours_necessaires'] = $joursNecessaires;
        $resultat['jours_planifiables'] = $this->compterJoursPlanifiables($debut, $fin);
        $resultat['date_fin_attendue'] = $dateFinAttendue?->toDateString();

        if (!$dateFinAttendue) {
            $resultat['message'] = 'La date de fin attendue n a pas pu etre calculee.';

            return $resultat;
        }

        if ($fin->lt($dateFinAttendue)) {
            $resultat['message'] = "Periode insuffisante : {$nombreSoutenances} soutenance(s) avec {$capaciteParJour} place(s) par jour necessitent {$joursNecessaires} jour(s) planifiable(s). "
                . "La date de fin doit etre le {$this->formatDate($dateFinAttendue)}, pas le {$this->formatDate($fin)}.";

            return $resultat;
        }

        if ($fin->gt($dateFinAttendue)) {
            $joursEnTrop = (int) $dateFinAttendue->diffInDays($fin);

            $resultat['message'] = "Periode trop longue : {$nombreSoutenances} soutenance(s) avec {$capaciteParJour} place(s) par jour se termineraient le {$this->formatDate($dateFinAttendue)}. "
                . "La date de fin saisie ({$this->formatDate($fin)}) laisse {$joursEnTrop} jour(s) apres la fin des soutenances.";

            return $resultat;
        }

        $resultat['valid'] = true;

        return $resultat;
    }

    private function dateFinAttendue(Carbon $dateDebut, int $joursNecessaires): ?Carbon
    {
        if ($joursNecessaires <= 0) {
            return null;
        }

        $date = $dateDebut->copy();
        $joursComptes = 0;

        while ($joursComptes < $joursNecessaires) {
            if (!$date->isSunday()) {
                $joursComptes++;
            }

            if ($joursComptes >= $joursNecessaires) {
                return $date->copy();
            }

            $date->addDay();
        }

        return null;
    }

    private function compterJoursPlanifiables(Carbon $dateDebut, Carbon $dateFin): int
    {
        $date = $dateDebut->copy();
        $total = 0;

        while ($date->lte($dateFin)) {
            if (!$date->isSunday()) {
                $total++;
            }

            $date->addDay();
        }

        return $total;
    }

    private function normaliserListe(array $valeurs): array
    {
        $resultat = [];

        foreach ($valeurs as $valeur) {
            $valeur = trim((string) $valeur);

            if ($valeur !== '') {
                $resultat[] = $valeur;
            }
        }

        return array_values(array_unique($resultat));
    }

    private function formatDate(Carbon $date): string
    {
        return $date->format('d/m/Y');
    }
}
