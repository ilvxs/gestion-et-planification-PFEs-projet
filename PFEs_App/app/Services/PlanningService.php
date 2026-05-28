<?php

namespace App\Services;

use App\Models\Pfe;
use App\Models\Professeur;
use App\Models\Soutenance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PlanningService
{
    private const DEFAULT_MAX_EXTRA_DAYS = 180;

    public function generer($dateDebut, $salles, $creneaux): array
    {
        set_time_limit(300);

        $errors = [];
        $warnings = [];

        $salles = $this->normaliserSalles($salles);
        $creneaux = $this->normaliserCreneaux($creneaux);
        $duree = (int) config('pfe.duree_soutenance_minutes', 60);
        $maxExtraDays = (int) config('pfe.max_extra_days', self::DEFAULT_MAX_EXTRA_DAYS);

        if ($maxExtraDays <= 0) {
            $maxExtraDays = self::DEFAULT_MAX_EXTRA_DAYS;
        }

        if (empty($salles)) {
            $errors[] = 'Aucune salle fournie pour générer le planning.';
        }

        if (empty($creneaux)) {
            $errors[] = 'Aucun creneau selectionne.';
        }

        if ($duree <= 0) {
            $errors[] = 'La durée de soutenance configurée doit être supérieure à 0 minute.';
        }

        try {
            $dateDepart = Carbon::parse($dateDebut)->startOfDay();
        } catch (\Throwable $e) {
            $dateDepart = Carbon::now()->startOfDay();
            $errors[] = 'Date de début invalide.';
        }

        $pfes = Pfe::with(['etudiant', 'encadrant'])->get()->values();
        $professeurs = Professeur::all()->values();

        $repartitionProfs = $this->initialiserRepartitionProfs($professeurs);
        $repartitionFilieres = [];
        $planning = [];

        if ($professeurs->count() < 3 && $pfes->isNotEmpty()) {
            $errors[] = 'Impossible de générer le planning : il faut au moins 3 professeurs.';
        }

        foreach ($pfes as $pfe) {
            $encadrant = $pfe->encadrant;

            if (!$encadrant) {
                $errors[] = "PFE {$pfe->id_pfe} non planifiable : aucun encadrant associé.";
                continue;
            }

            if (!$pfe->etudiant) {
                $warnings[] = "PFE {$pfe->id_pfe} : étudiant introuvable, filière considérée comme Inconnue.";
            }

            $jurysPossibles = $professeurs->filter(function ($prof) use ($encadrant) {
                return (int) $prof->id_professeur !== (int) $encadrant->id_professeur;
            })->count();

            if ($jurysPossibles < 2) {
                $errors[] = "PFE {$pfe->id_pfe} non planifiable : moins de deux jurys distincts disponibles hors encadrant.";
                continue;
            }

            $infoPossibles = $this->isInfo($encadrant->specialite) ? 1 : 0;

            $infoPossibles += $professeurs->filter(function ($prof) use ($encadrant) {
                return (int) $prof->id_professeur !== (int) $encadrant->id_professeur
                    && $this->isInfo($prof->specialite);
            })->count();

            if ($infoPossibles < 2) {
                $errors[] = "PFE {$pfe->id_pfe} non planifiable : impossible d'avoir au moins 2 professeurs de spécialité informatique.";
            }
        }

        if (!empty($errors)) {
            return [
                'created' => 0,
                'errors' => $errors,
                'warnings' => $warnings,
                'planning' => [],
                'repartition_profs' => $repartitionProfs,
                'repartition_filieres' => $repartitionFilieres,
            ];
        }

        $maxParticipations = $professeurs->count() > 0
            ? (int) ceil(($pfes->count() * 3) / $professeurs->count())
            : 0;

        $slots = $this->genererSlots($dateDepart, $salles, $creneaux, $maxExtraDays);
        $pfesRestants = $pfes;

        foreach ($slots as $slot) {
            if ($pfesRestants->isEmpty()) {
                break;
            }

            if ($this->salleOccupee($planning, $slot['date'], $slot['heure'], $slot['salle'], $duree)) {
                continue;
            }

            $meilleureAffectation = null;

            foreach ($pfesRestants as $pfe) {
                $affectation = $this->meilleureAffectationPourPfe(
                    $pfe,
                    $professeurs,
                    $planning,
                    $slot,
                    $repartitionProfs,
                    $repartitionFilieres,
                    $maxParticipations,
                    $duree
                );

                if (!$affectation) {
                    continue;
                }

                $scoreTri = $affectation['score'] - $this->scoreDifficultePfe($pfe);

                if (
                    !$meilleureAffectation ||
                    $scoreTri < $meilleureAffectation['score_tri'] ||
                    (
                        $scoreTri === $meilleureAffectation['score_tri'] &&
                        (int) $pfe->id_pfe < (int) $meilleureAffectation['pfe']->id_pfe
                    )
                ) {
                    $meilleureAffectation = [
                        'pfe' => $pfe,
                        'jury1' => $affectation['jury1'],
                        'jury2' => $affectation['jury2'],
                        'score_tri' => $scoreTri,
                        'warning' => $affectation['warning'],
                    ];
                }
            }

            if (!$meilleureAffectation) {
                continue;
            }

            $pfe = $meilleureAffectation['pfe'];
            $encadrant = $pfe->encadrant;
            $jury1 = $meilleureAffectation['jury1'];
            $jury2 = $meilleureAffectation['jury2'];

            if (!$this->affectationRespecteContraintesDures($planning, $slot, $pfe, $jury1, $jury2, $duree)) {
                $errors[] = "PFE {$pfe->id_pfe} : affectation rejetée car une contrainte dure serait violée.";
                break;
            }

            if ($meilleureAffectation['warning']) {
                $warnings[] = $meilleureAffectation['warning'];
            }

            $planning[] = $this->formatPlanningItem($slot, $pfe, $encadrant, $jury1, $jury2);

            $this->mettreAJourRepartitionProfs($repartitionProfs, [$encadrant, $jury1, $jury2], $slot['date'], $slot['heure']);
            $this->mettreAJourRepartitionFilieres($repartitionFilieres, $slot['date'], $this->filierePfe($pfe));

            $pfesRestants = $pfesRestants->reject(function ($item) use ($pfe) {
                return (int) $item->id_pfe === (int) $pfe->id_pfe;
            })->values();
        }

        foreach ($pfesRestants as $pfe) {
            $errors[] = $this->diagnostiquerEchecPfe(
                $pfe,
                $professeurs,
                $planning,
                $repartitionProfs,
                $maxParticipations,
                $duree
            );
        }

        $created = 0;

        if (empty($errors)) {
            try {
                $this->sauvegarderPlanning($planning);
                $created = count($planning);
            } catch (\Throwable $e) {
                $errors[] = "Erreur lors de l'enregistrement du planning : {$e->getMessage()}";
            }
        }

        return [
            'created' => $created,
            'errors' => $errors,
            'warnings' => $warnings,
            'planning' => empty($errors) ? $planning : [],
            'repartition_profs' => $this->ordonnerRepartition($repartitionProfs),
            'repartition_filieres' => $this->ordonnerRepartition($repartitionFilieres),
        ];
    }

    private function meilleureAffectationPourPfe(
        $pfe,
        $professeurs,
        array $planning,
        array $slot,
        array $repartitionProfs,
        array $repartitionFilieres,
        int $maxParticipations,
        int $duree
    ): ?array {
        $encadrant = $pfe->encadrant;

        if (!$encadrant) {
            return null;
        }

        if (!$this->profDisponiblePourCreneau(
            $planning,
            (int) $encadrant->id_professeur,
            $slot['date'],
            $slot['heure'],
            $duree
        )) {
            return null;
        }

        $anglaisObligatoire = $this->anglaisObligatoire(
            $pfe,
            $encadrant,
            $professeurs,
            $repartitionProfs,
            $maxParticipations
        );

        $candidats = $professeurs->filter(function ($prof) use ($encadrant, $planning, $slot, $duree) {
            if ((int) $prof->id_professeur === (int) $encadrant->id_professeur) {
                return false;
            }

            return $this->profDisponiblePourCreneau(
                $planning,
                (int) $prof->id_professeur,
                $slot['date'],
                $slot['heure'],
                $duree
            );
        })->values();

        if ($candidats->count() < 2) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Premier essai :
        | Si le PFE est anglais, on essaie d'abord avec un professeur anglais.
        |--------------------------------------------------------------------------
        */

        $best = $this->chercherMeilleurePaireJurys(
            $pfe,
            $encadrant,
            $candidats,
            $slot,
            $repartitionProfs,
            $repartitionFilieres,
            $maxParticipations,
            $anglaisObligatoire
        );

        if ($best) {
            return $best;
        }

        /*
        |--------------------------------------------------------------------------
        | Deuxième essai :
        | Si aucun professeur anglais ne peut être placé sans conflit,
        | on relâche la contrainte anglais et on ajoute un warning.
        |--------------------------------------------------------------------------
        */

        if ($this->isEnglish($pfe->langue) && $anglaisObligatoire) {
            $bestRelaxed = $this->chercherMeilleurePaireJurys(
                $pfe,
                $encadrant,
                $candidats,
                $slot,
                $repartitionProfs,
                $repartitionFilieres,
                $maxParticipations,
                false
            );

            if ($bestRelaxed) {
                $bestRelaxed['warning'] = $this->warningAnglaisRelache($pfe);
                $bestRelaxed['score'] += 300;

                return $bestRelaxed;
            }
        }

        return null;
    }

    private function chercherMeilleurePaireJurys(
        $pfe,
        $encadrant,
        $candidats,
        array $slot,
        array $repartitionProfs,
        array $repartitionFilieres,
        int $maxParticipations,
        bool $exigerAnglais
    ): ?array {
        $best = null;

        for ($i = 0; $i < $candidats->count(); $i++) {
            for ($j = $i + 1; $j < $candidats->count(); $j++) {
                $jury1 = $candidats[$i];
                $jury2 = $candidats[$j];

                if ((int) $jury1->id_professeur === (int) $jury2->id_professeur) {
                    continue;
                }

                if (!$this->aMinimumDeuxInfo($encadrant, $jury1, $jury2)) {
                    continue;
                }

                $anglaisPresent = $this->anglaisPresent($encadrant, $jury1, $jury2);

                if ($exigerAnglais && !$anglaisPresent) {
                    continue;
                }

                $score = $this->scoreAffectation(
                    $pfe,
                    $encadrant,
                    $jury1,
                    $jury2,
                    $slot['date'],
                    $slot['heure'],
                    $repartitionProfs,
                    $repartitionFilieres,
                    $maxParticipations
                );

                $warning = null;

                if ($this->isEnglish($pfe->langue) && !$anglaisPresent) {
                    $warning = $this->warningAnglaisRelache($pfe);
                    $score += 120;
                }

                if (!$best || $score < $best['score']) {
                    $best = [
                        'jury1' => $jury1,
                        'jury2' => $jury2,
                        'score' => $score,
                        'warning' => $warning,
                    ];
                }
            }
        }

        return $best;
    }
    private function affectationRespecteContraintesDures(
        array $planning,
        array $slot,
        $pfe,
        $jury1,
        $jury2,
        int $duree
    ): bool {
        if (Carbon::parse($slot['date'])->dayOfWeek === Carbon::SUNDAY) {
            return false;
        }

        if ($this->salleOccupee($planning, $slot['date'], $slot['heure'], $slot['salle'], $duree)) {
            return false;
        }

        $encadrant = $pfe->encadrant;

        if (!$encadrant || !$jury1 || !$jury2) {
            return false;
        }

        $ids = [
            (int) $encadrant->id_professeur,
            (int) $jury1->id_professeur,
            (int) $jury2->id_professeur,
        ];

        if (count(array_unique($ids)) !== 3) {
            return false;
        }

        foreach ($ids as $idProf) {
            if (!$this->profDisponiblePourCreneau($planning, $idProf, $slot['date'], $slot['heure'], $duree)) {
                return false;
            }
        }

        return $this->aMinimumDeuxInfo($encadrant, $jury1, $jury2);
    }

    private function salleOccupee(array $planning, string $date, string $heure, string $salle, int $duree): bool
    {
        $nouvelleMinute = $this->minutesDepuisMinuit($heure);

        foreach ($planning as $item) {
            if ($item['date'] !== $date || $item['salle'] !== $salle) {
                continue;
            }

            if ($item['heure'] === $heure) {
                return true;
            }

            $ancienneMinute = $this->minutesDepuisMinuit($item['heure']);

            if ($nouvelleMinute !== null && $ancienneMinute !== null && abs($nouvelleMinute - $ancienneMinute) < $duree) {
                return true;
            }
        }

        return false;
    }

    private function profDisponiblePourCreneau(array $planning, int $idProf, string $date, string $heure, int $duree): bool
    {
        return !$this->profOccupe($planning, $idProf, $date, $heure)
            && !$this->profConsecutif($planning, $idProf, $date, $heure, $duree);
    }

    private function profOccupe(array $planning, int $idProf, string $date, string $heure): bool
    {
        foreach ($planning as $soutenance) {
            if ($soutenance['date'] !== $date || $soutenance['heure'] !== $heure) {
                continue;
            }

            if (
                (int) $soutenance['id_encadrant'] === $idProf ||
                (int) $soutenance['id_jury1'] === $idProf ||
                (int) $soutenance['id_jury2'] === $idProf
            ) {
                return true;
            }
        }

        return false;
    }

    private function profConsecutif(array $planning, int $idProf, string $date, string $heure, int $duree): bool
    {
        $nouvelleMinute = $this->minutesDepuisMinuit($heure);

        if ($nouvelleMinute === null) {
            return false;
        }

        foreach ($planning as $soutenance) {
            if ($soutenance['date'] !== $date) {
                continue;
            }

            $profPresent =
                (int) $soutenance['id_encadrant'] === $idProf ||
                (int) $soutenance['id_jury1'] === $idProf ||
                (int) $soutenance['id_jury2'] === $idProf;

            if (!$profPresent) {
                continue;
            }

            $ancienneMinute = $this->minutesDepuisMinuit($soutenance['heure']);

            if ($ancienneMinute === null) {
                continue;
            }

            $difference = abs($nouvelleMinute - $ancienneMinute);

            if ($difference > 0 && $difference <= $duree) {
                return true;
            }
        }

        return false;
    }

    private function scoreAffectation(
        $pfe,
        $encadrant,
        $jury1,
        $jury2,
        string $date,
        string $heure,
        array $repartitionProfs,
        array $repartitionFilieres,
        int $maxParticipations
    ): float {
        $score = 0.0;

        $filiere = $this->filierePfe($pfe);
        $filiereCount = $repartitionFilieres[$date][$filiere] ?? 0;
        $score += ($filiereCount * $filiereCount * 30) + ($filiereCount * 10);

        foreach ([$encadrant, $jury1, $jury2] as $prof) {
            $id = (int) $prof->id_professeur;

            $total = $repartitionProfs[$id]['total'] ?? 0;
            $parJour = $repartitionProfs[$id]['par_jour'][$date] ?? 0;
            $parHeure = $repartitionProfs[$id]['par_heure'][$heure] ?? 0;

            $score += $total * 8;
            $score += ($parJour * $parJour * 18) + ($parJour * 8);
            $score += ($parHeure * $parHeure * 12) + ($parHeure * 4);

            if ($maxParticipations > 0 && $total >= $maxParticipations) {
                $score += (($total - $maxParticipations) + 1) * 25;
            }
        }

        if ($this->isEnglish($pfe->langue)) {
            $score += $this->anglaisPresent($encadrant, $jury1, $jury2) ? -35 : 120;
        } else {
            foreach ([$jury1, $jury2] as $prof) {
                if ($this->isEnglish($prof->specialite)) {
                    $score += 8;
                }
            }
        }

        return $score;
    }

    private function scoreDifficultePfe($pfe): int
    {
        $score = 0;
        $encadrant = $pfe->encadrant;

        if (!$encadrant || !$this->isInfo($encadrant->specialite)) {
            $score += 40;
        }

        if ($this->isEnglish($pfe->langue) && (!$encadrant || !$this->isEnglish($encadrant->specialite))) {
            $score += 30;
        }

        return $score;
    }

    private function anglaisObligatoire($pfe, $encadrant, $professeurs, array $repartitionProfs, int $maxParticipations): bool
    {
        if (!$this->isEnglish($pfe->langue)) {
            return false;
        }

        if ($encadrant && $this->isEnglish($encadrant->specialite)) {
            return false;
        }

        $profsAnglais = $professeurs->filter(function ($prof) {
            return $this->isEnglish($prof->specialite);
        });

        if ($profsAnglais->isEmpty()) {
            return false;
        }

        foreach ($profsAnglais as $prof) {
            $total = $repartitionProfs[(int) $prof->id_professeur]['total'] ?? 0;

            if ($total < $maxParticipations) {
                return true;
            }
        }

        return false;
    }

    private function warningAnglaisRelache($pfe): string
    {
        return "PFE {$pfe->id_pfe} en anglais planifié sans professeur anglais : "
            . "tous les professeurs anglais étaient soit indisponibles "
            . "(conflit horaire ou soutenance consécutive), "
            . "soit déjà au niveau de charge équitable.";
    }
   
    private function diagnostiquerEchecPfe(
        $pfe,
        $professeurs,
        array $planning,
        array $repartitionProfs,
        int $maxParticipations,
        int $duree
    ): string {
        $encadrant = $pfe->encadrant;

        if (!$encadrant) {
            return "PFE {$pfe->id_pfe} non planifié : aucun encadrant associé.";
        }

        $jurysPossibles = $professeurs->filter(function ($prof) use ($encadrant) {
            return (int) $prof->id_professeur !== (int) $encadrant->id_professeur;
        })->count();

        if ($jurysPossibles < 2) {
            return "PFE {$pfe->id_pfe} non planifié : moins de deux professeurs disponibles comme jurys.";
        }

        $infoPossibles = $this->isInfo($encadrant->specialite) ? 1 : 0;

        $infoPossibles += $professeurs->filter(function ($prof) use ($encadrant) {
            return (int) $prof->id_professeur !== (int) $encadrant->id_professeur
                && $this->isInfo($prof->specialite);
        })->count();

        if ($infoPossibles < 2) {
            return "PFE {$pfe->id_pfe} non planifié : la contrainte obligatoire des 2 professeurs informatique est impossible.";
        }

        if (
            $this->isEnglish($pfe->langue) &&
            !$this->isEnglish($encadrant->specialite) &&
            $this->anglaisObligatoire($pfe, $encadrant, $professeurs, $repartitionProfs, $maxParticipations)
        ) {
            return "PFE {$pfe->id_pfe} non planifié : aucun créneau trouvé. "
                . "Le problème peut venir d'un conflit horaire, d'une soutenance consécutive, "
                . "ou de la contrainte obligatoire des 2 professeurs informatique.";
        }

        return "PFE {$pfe->id_pfe} non planifié : aucun créneau trouvé sans conflit de salle, conflit professeur ou soutenance consécutive. Augmentez les jours, les salles ou les créneaux.";
    }

    private function sauvegarderPlanning(array $planning): void
    {
        $model = new Soutenance();

        $usesTimestamps = $model->usesTimestamps();
        $createdAtColumn = $model->getCreatedAtColumn();
        $updatedAtColumn = $model->getUpdatedAtColumn();

        $now = now();

        $rows = [];

        foreach ($planning as $item) {

            $row = [
                'date_soutenance' => $item['date'],
                'heure_debut' => $item['heure'],
                'salle' => $item['salle'],
                'id_pfe' => $item['id_pfe'],
                'id_jury1' => $item['id_jury1'],
                'id_jury2' => $item['id_jury2'],
            ];

            if ($usesTimestamps) {
                $row[$createdAtColumn] = $now;
                $row[$updatedAtColumn] = $now;
            }

            $rows[] = $row;
        }

        DB::transaction(function () use ($rows) {

            /*
            |--------------------------------------------------------------------------
            | Supprimer l'ancien planning
            |--------------------------------------------------------------------------
            */

            Soutenance::query()->delete();

            /*
            |--------------------------------------------------------------------------
            | Réinsérer le nouveau planning
            |--------------------------------------------------------------------------
            */

            if (!empty($rows)) {
                Soutenance::insert($rows);
            }
        });
    }

    private function formatPlanningItem(array $slot, $pfe, $encadrant, $jury1, $jury2): array
    {
        return [
            'date' => $slot['date'],
            'heure' => $slot['heure'],
            'salle' => $slot['salle'],
            'filiere' => $this->filierePfe($pfe),
            'langue' => $pfe->langue ?? '-',
            'pfe' => $pfe->sujet ?? '-',
            'encadrant' => $this->nomComplet($encadrant),
            'specialite_encadrant' => $encadrant->specialite ?? '-',
            'jury1' => $this->nomComplet($jury1),
            'specialite_jury1' => $jury1->specialite ?? '-',
            'jury2' => $this->nomComplet($jury2),
            'specialite_jury2' => $jury2->specialite ?? '-',
            'id_pfe' => $pfe->id_pfe,
            'id_encadrant' => $encadrant->id_professeur,
            'id_jury1' => $jury1->id_professeur,
            'id_jury2' => $jury2->id_professeur,
        ];
    }

    private function mettreAJourRepartitionProfs(array &$repartitionProfs, array $professeurs, string $date, string $heure): void
    {
        foreach ($professeurs as $prof) {
            $id = (int) $prof->id_professeur;

            if (!isset($repartitionProfs[$id])) {
                continue;
            }

            $repartitionProfs[$id]['total']++;

            if (!isset($repartitionProfs[$id]['par_jour'][$date])) {
                $repartitionProfs[$id]['par_jour'][$date] = 0;
            }

            if (!isset($repartitionProfs[$id]['par_heure'][$heure])) {
                $repartitionProfs[$id]['par_heure'][$heure] = 0;
            }

            $repartitionProfs[$id]['par_jour'][$date]++;
            $repartitionProfs[$id]['par_heure'][$heure]++;
        }
    }

    private function mettreAJourRepartitionFilieres(array &$repartitionFilieres, string $date, string $filiere): void
    {
        if (!isset($repartitionFilieres[$date])) {
            $repartitionFilieres[$date] = [];
        }

        if (!isset($repartitionFilieres[$date][$filiere])) {
            $repartitionFilieres[$date][$filiere] = 0;
        }

        $repartitionFilieres[$date][$filiere]++;
    }

    private function initialiserRepartitionProfs($professeurs): array
    {
        $stats = [];

        foreach ($professeurs as $prof) {
            $stats[(int) $prof->id_professeur] = [
                'id_professeur' => $prof->id_professeur,
                'nom' => $this->nomComplet($prof),
                'specialite' => $prof->specialite ?? '-',
                'total' => 0,
                'par_jour' => [],
                'par_heure' => [],
            ];
        }

        return $stats;
    }

    private function genererSlots(Carbon $dateDepart, array $salles, array $creneaux, int $maxExtraDays): array
    {
        $slots = [];
        $date = $dateDepart->copy();

        for ($jour = 0; $jour <= $maxExtraDays; $jour++) {
            if ($date->dayOfWeek !== Carbon::SUNDAY) {
                foreach ($creneaux as $heure) {
                    foreach ($salles as $salle) {
                        $slots[] = [
                            'date' => $date->toDateString(),
                            'heure' => $heure,
                            'salle' => $salle,
                        ];
                    }
                }
            }

            $date->addDay();
        }

        return $slots;
    }

    private function normaliserSalles($salles): array
    {
        if ($salles instanceof \Illuminate\Support\Collection) {
            $salles = $salles->all();
        }

        $result = [];

        foreach ((array) $salles as $salle) {
            $salle = trim((string) $salle);

            if ($salle !== '') {
                $result[] = $salle;
            }
        }

        return array_values(array_unique($result));
    }

    private function normaliserCreneaux($creneaux): array
    {
        if ($creneaux instanceof \Illuminate\Support\Collection) {
            $creneaux = $creneaux->all();
        }

        $result = [];

        foreach ((array) $creneaux as $creneau) {
            $heure = $this->normaliserHeure($creneau);

            if ($heure !== null) {
                $result[] = $heure;
            }
        }

        $result = array_values(array_unique($result));

        usort($result, function ($a, $b) {
            return ($this->minutesDepuisMinuit($a) ?? 0) <=> ($this->minutesDepuisMinuit($b) ?? 0);
        });

        return $result;
    }

    private function normaliserHeure($heure): ?string
    {
        $heure = trim((string) $heure);

        if (!preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $heure, $matches)) {
            return null;
        }

        $h = (int) $matches[1];
        $m = (int) $matches[2];

        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $h, $m);
    }

    private function minutesDepuisMinuit(string $heure): ?int
    {
        $heure = $this->normaliserHeure($heure);

        if ($heure === null) {
            return null;
        }

        [$h, $m] = array_map('intval', explode(':', $heure));

        return ($h * 60) + $m;
    }

    private function aMinimumDeuxInfo($encadrant, $jury1, $jury2): bool
    {
        $count = 0;

        foreach ([$encadrant, $jury1, $jury2] as $prof) {
            if ($prof && $this->isInfo($prof->specialite)) {
                $count++;
            }
        }

        return $count >= 2;
    }

    private function anglaisPresent($encadrant, $jury1, $jury2): bool
    {
        foreach ([$encadrant, $jury1, $jury2] as $prof) {
            if ($prof && $this->isEnglish($prof->specialite)) {
                return true;
            }
        }

        return false;
    }

    private function isEnglish(?string $text): bool
    {
        if (!$text) {
            return false;
        }

        $text = strtolower(trim($text));

        return in_array($text, [
            'anglais',
            'english',
            'eng',
            'en',
        ], true);
    }

    private function isInfo(?string $text): bool
    {
        if (!$text) {
            return false;
        }

        $text = strtolower(trim($text));

        return in_array($text, [
            'informatique',
            'info',
            'computer',
            'computer science',
            'software',
            'cs',
        ], true);
    }

    private function filierePfe($pfe): string
    {
        return $pfe->etudiant->filiere ?? 'Inconnue';
    }

    private function nomComplet($prof): string
    {
        return trim(($prof->nom ?? '') . ' ' . ($prof->prenom ?? '')) ?: '-';
    }

    private function ordonnerRepartition(array $data): array
    {
        ksort($data);

        foreach ($data as &$value) {
            if (is_array($value)) {
                ksort($value);

                if (isset($value['par_jour']) && is_array($value['par_jour'])) {
                    ksort($value['par_jour']);
                }

                if (isset($value['par_heure']) && is_array($value['par_heure'])) {
                    ksort($value['par_heure']);
                }
            }
        }

        return $data;
    }
}
