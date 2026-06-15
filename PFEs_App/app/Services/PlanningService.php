<?php

namespace App\Services;

use App\Models\Pfe;
use App\Models\Professeur;
use App\Models\Salle;
use App\Models\Soutenance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PlanningService
{
    private const DEFAULT_MAX_EXTRA_DAYS = 15;

    /*nombre maximum de PFEs testes pour chaque slot*/
    private const MAX_PFES_TESTES_PAR_SLOT = 15;

    public function generer($dateDebut, $salles, $creneaux, $dateFin = null): array
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

        $dateLimite = null;

        if ($dateFin !== null) {
            try {
                $dateLimite = Carbon::parse($dateFin)->startOfDay();

                if ($dateLimite->lt($dateDepart)) {
                    $errors[] = 'Date de fin invalide : elle doit etre apres ou egale a la date de debut.';
                }
            } catch (\Throwable $e) {
                $errors[] = 'Date de fin invalide.';
            }
        }

        $pfes = Pfe::with(['etudiant', 'encadrant'])->get()->values();
        $professeurs = Professeur::all()->values();

        $pfeMeta = $this->initialiserPfeMeta($pfes);
        $profMeta = $this->initialiserProfMeta($professeurs);

        $repartitionProfs = $this->initialiserRepartitionProfs($professeurs);
        $repartitionFilieres = [];
        $planning = [];

        $totalProfesseurs = $professeurs->count();

        $totalProfesseursInfo = collect($profMeta)
            ->filter(function ($meta) {
                return $meta['is_info'];
            })
            ->count();

        $idsProfsAnglais = collect($profMeta)
            ->filter(function ($meta) {
                return $meta['is_english'];
            })
            ->keys()
            ->map(function ($id) {
                return (int) $id;
            })
            ->values()
            ->all();

        $occupation = [
            'salles' => [],
            'profs' => [],
        ];

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
                $warnings[] = "PFE {$pfe->id_pfe} : étudiant introuvable, considéré comme Inconnue.";
            }

            $jurysPossibles = $totalProfesseurs - 1;

            if ($jurysPossibles < 2) {
                $errors[] = "PFE {$pfe->id_pfe} non planifiable : moins de deux jurys distincts disponibles hors encadrant.";
                continue;
            }

            if ($totalProfesseursInfo < 2) {
                $errors[] = "PFE {$pfe->id_pfe} non planifiable : impossible d'avoir au moins 2 professeurs de spécialité informatique.";
            }
        }

        if (!empty($errors)) {
            return [
                'created' => 0,
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        $maxParticipations = $professeurs->count() > 0
            ? (int) ceil(($pfes->count() * 3) / $professeurs->count())
            : 0;

        $slots = $this->genererSlots($dateDepart, $salles, $creneaux, $maxExtraDays, $dateLimite);
        $pfesRestants = $pfes->keyBy('id_pfe');

        foreach ($slots as $slot) {
            if ($pfesRestants->isEmpty()) {
                break;
            }

            if ($this->salleOccupeeRapide($occupation, $slot['date'], $slot['minute'], $slot['id_salle'], $duree)) {
                continue;
            }

            /* professeurs disponibles pour ce slot, on les calcule une seule fois pour éviter de refaire le meme filtre pour chaque PFE restant*/
            $professeursDisponiblesSlot = $this->professeursDisponiblesPourSlot(
                $professeurs,
                $occupation,
                $slot,
                $duree
            );

            if ($professeursDisponiblesSlot->count() < 3) {
                continue;
            }

            $meilleureAffectation = null;

            /* au lieu de tester tous les PFEs restants, on teste d'abord les PFEs les plus prioritaires : difficiles + filière moins utilisée ce jour */

            $pfesATester = $this->selectionnerPfesATesterPourSlot(
                $pfesRestants,
                $pfeMeta,
                $repartitionFilieres,
                $slot['date']
            );

            foreach ($pfesATester as $pfe) {
                $affectation = $this->meilleureAffectationPourPfe(
                    $pfe,
                    $professeursDisponiblesSlot,
                    $pfeMeta,
                    $profMeta,
                    $idsProfsAnglais,
                    $occupation,
                    $slot,
                    $repartitionProfs,
                    $repartitionFilieres,
                    $maxParticipations,
                    $duree
                );

                if (!$affectation) {
                    continue;
                }

                $scoreTri = $affectation['score'] - ($pfeMeta[(int) $pfe->id_pfe]['difficulty'] ?? 0);

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

            if (!$this->affectationRespecteContraintesDures($occupation, $slot, $pfe, $jury1, $jury2, $duree)) {
                $errors[] = "PFE {$pfe->id_pfe} : affectation rejetée car une contrainte dure serait violée.";
                break;
            }

            if ($meilleureAffectation['warning']) {
                $warnings[] = $meilleureAffectation['warning'];
            }

            $planning[] = $this->formatPlanningItem($slot, $pfe, $encadrant, $jury1, $jury2);

            $this->ajouterOccupation(
                $occupation,
                $slot['date'],
                $slot['minute'],
                $slot['id_salle'],
                [$encadrant, $jury1, $jury2]
            );
            $this->mettreAJourRepartitionProfs($repartitionProfs, [$encadrant, $jury1, $jury2], $slot['date'], $slot['heure']);
            $this->mettreAJourRepartitionFilieres($repartitionFilieres,  $slot['date'], $pfeMeta[(int) $pfe->id_pfe]['filiere'] ?? $this->filierePfe($pfe));
            $pfesRestants->forget((int) $pfe->id_pfe);
        }

        foreach ($pfesRestants as $pfe) {
            $errors[] = $this->diagnostiquerEchecPfe(
                $pfe,
                $professeurs,
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
            ];
    }

    private function meilleureAffectationPourPfe(
        $pfe,
        $professeursDisponiblesSlot,
        array $pfeMeta,
        array $profMeta,
        array $idsProfsAnglais,
        array $occupation,
        array $slot,
        array $repartitionProfs,
        array $repartitionFilieres,
        int $maxParticipations,
        int $duree
    ): ?array {
        $encadrant = $pfe->encadrant;
        $pfeAnglais = $pfeMeta[(int) $pfe->id_pfe]['is_english']
            ?? $this->isEnglish($pfe->langue);

        if (!$encadrant) {
            return null;
        }

        if (!$this->profDisponiblePourCreneauRapide(
            $occupation,
            (int) $encadrant->id_professeur,
            $slot['date'],
            $slot['minute'],
            $duree
        )) {
            return null;
        }

        $anglaisObligatoire = $this->anglaisObligatoireRapide(
            $pfeAnglais,
            $encadrant,
            $idsProfsAnglais,
            $profMeta,
            $repartitionProfs,
            $maxParticipations
        );

        $candidats = $professeursDisponiblesSlot->filter(function ($prof) use ($encadrant) {
            return (int) $prof->id_professeur !== (int) $encadrant->id_professeur;
        })->values();

        if ($candidats->count() < 2) {
            return null;
        }

        return $this->chercherMeilleurePaireJurys(
            $pfe,
            $encadrant,
            $candidats,
            $pfeMeta,
            $profMeta,
            $slot,
            $repartitionProfs,
            $repartitionFilieres,
            $maxParticipations,
            $anglaisObligatoire
        );
    }

    private function chercherMeilleurePaireJurys(
        $pfe,
        $encadrant,
        $candidats,
        array $pfeMeta,
        array $profMeta,
        array $slot,
        array $repartitionProfs,
        array $repartitionFilieres,
        int $maxParticipations,
        bool $exigerAnglais
    ): ?array {
        $best = null;
        $bestSansAnglais = null;

        $pfeAnglais = $pfeMeta[(int) $pfe->id_pfe]['is_english']
            ?? $this->isEnglish($pfe->langue);

        $encadrantInfo = $this->profIsInfo($encadrant, $profMeta);

        $candidatsInfo = $candidats->filter(function ($prof) use ($profMeta) {
            return $this->profIsInfo($prof, $profMeta);
        })->values();

        /* si l'encadrant n'est pas info les deux jurys doivent etre info */

        if (!$encadrantInfo) {
            if ($candidatsInfo->count() < 2) {
                return null;
            }

            $candidats = $candidatsInfo;
        }

        $count = $candidats->count();

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {

                $jury1 = $candidats[$i];
                $jury2 = $candidats[$j];

                if ((int) $jury1->id_professeur === (int) $jury2->id_professeur) {
                    continue;
                }

                $jury1Info = $this->profIsInfo($jury1, $profMeta);
                $jury2Info = $this->profIsInfo($jury2, $profMeta);

                if ($encadrantInfo) {
                    if (!$jury1Info && !$jury2Info) {
                        continue;
                    }
                } else {
                    if (!$jury1Info || !$jury2Info) {
                        continue;
                    }
                }

                $anglaisPresent = $this->anglaisPresentMeta(
                    $encadrant,
                    $jury1,
                    $jury2,
                    $profMeta
                );

                $score = $this->scoreAffectation(
                    $pfe,
                    $encadrant,
                    $jury1,
                    $jury2,
                    $pfeMeta,
                    $profMeta,
                    $slot['date'],
                    $slot['heure'],
                    $repartitionProfs,
                    $repartitionFilieres,
                    $maxParticipations
                );

                $warning = null;

                /*PFE anglais sans professeur anglais: on ne rejette plus directement la paire, on la garde comme solution de secours avec une penalite */

                if ($pfeAnglais && !$anglaisPresent) {
                    $warning = $this->warningAnglaisRelache($pfe);

                    /* utiliser un PFE anglais sans prof anglais est moins bon*/
                    $score += 120;

                    /*si l'anglais etait demande, cette solution devient fallback*/

                    if ($exigerAnglais) {
                        $score += 300;
                    }

                    if (!$bestSansAnglais || $score < $bestSansAnglais['score']) {
                        $bestSansAnglais = [
                            'jury1' => $jury1,
                            'jury2' => $jury2,
                            'score' => $score,
                            'warning' => $warning,
                        ];
                    }

                    /* si anglais obligatoire, on préfère continuer a chercher une solution avec professeur anglais */

                    if ($exigerAnglais) {
                        continue;
                    }
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

        return $best ?? $bestSansAnglais;
    }
    private function affectationRespecteContraintesDures(
        array $occupation,
        array $slot,
        $pfe,
        $jury1,
        $jury2,
        int $duree
    ): bool {
        if (Carbon::parse($slot['date'])->dayOfWeek === Carbon::SUNDAY) {
            return false;
        }

        if ($this->salleOccupeeRapide($occupation, $slot['date'], $slot['minute'], $slot['id_salle'], $duree)) {
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
            if (!$this->profDisponiblePourCreneauRapide($occupation, $idProf, $slot['date'], $slot['minute'], $duree)) {
                return false;
            }
        }

        return $this->aMinimumDeuxInfo($encadrant, $jury1, $jury2);
    }

    private function salleOccupeeRapide(
        array $occupation,
        string $date,
        int $nouvelleMinute,
        int $idSalle,
        int $duree
    ): bool {
        $minutesOccupees = $occupation['salles'][$date][$idSalle] ?? [];

        foreach ($minutesOccupees as $ancienneMinute) {
            if (abs($nouvelleMinute - $ancienneMinute) < $duree) {
                return true;
            }
        }

        return false;
    }

    private function profDisponiblePourCreneauRapide(
        array $occupation,
        int $idProf,
        string $date,
        int $nouvelleMinute,
        int $duree
    ): bool {
        $minutesOccupees = $occupation['profs'][$date][$idProf] ?? [];

        foreach ($minutesOccupees as $ancienneMinute) {
            $difference = abs($nouvelleMinute - $ancienneMinute);

            if ($difference === 0) {
                return false;
            }

            if ($difference > 0 && $difference <= $duree) {
                return false;
            }
        }

        return true;
    }

    private function ajouterOccupation(
        array &$occupation,
        string $date,
        int $minute,
        int $idSalle,
        array $professeurs
    ): void {
        if (!isset($occupation['salles'][$date])) {
            $occupation['salles'][$date] = [];
        }

        if (!isset($occupation['salles'][$date][$idSalle])) {
            $occupation['salles'][$date][$idSalle] = [];
        }

        $occupation['salles'][$date][$idSalle][] = $minute;

        foreach ($professeurs as $prof) {
            $idProf = (int) $prof->id_professeur;

            if (!isset($occupation['profs'][$date])) {
                $occupation['profs'][$date] = [];
            }

            if (!isset($occupation['profs'][$date][$idProf])) {
                $occupation['profs'][$date][$idProf] = [];
            }

            $occupation['profs'][$date][$idProf][] = $minute;
        }
    }

    private function professeursDisponiblesPourSlot(
        $professeurs,
        array $occupation,
        array $slot,
        int $duree
    ) {
        return $professeurs->filter(function ($prof) use ($occupation, $slot, $duree) {
            return $this->profDisponiblePourCreneauRapide(
                $occupation,
                (int) $prof->id_professeur,
                $slot['date'],
                $slot['minute'],
                $duree
            );
        })->values();
    }

    private function scoreAffectation(
        $pfe,
        $encadrant,
        $jury1,
        $jury2,
        array $pfeMeta,
        array $profMeta,
        string $date,
        string $heure,
        array $repartitionProfs,
        array $repartitionFilieres,
        int $maxParticipations
    ): float {
        $score = 0.0;

        $filiere = $pfeMeta[(int) $pfe->id_pfe]['filiere']
            ?? $this->filierePfe($pfe);

        $pfeAnglais = $pfeMeta[(int) $pfe->id_pfe]['is_english']
            ?? $this->isEnglish($pfe->langue);
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

        if ($pfeAnglais) {
            $score += $this->anglaisPresentMeta($encadrant, $jury1, $jury2, $profMeta) ? -35 : 120;
        } else {
            foreach ([$jury1, $jury2] as $prof) {
                if ($this->profIsEnglish($prof, $profMeta)) {
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

    private function anglaisObligatoireRapide(
        bool $pfeAnglais,
        $encadrant,
        array $idsProfsAnglais,
        array $profMeta,
        array $repartitionProfs,
        int $maxParticipations
    ): bool {
        if (!$pfeAnglais) {
            return false;
        }

        if ($encadrant && $this->profIsEnglish($encadrant, $profMeta)) {
            return false;
        }

        if (empty($idsProfsAnglais)) {
            return false;
        }

        foreach ($idsProfsAnglais as $idProf) {
            $total = $repartitionProfs[$idProf]['total'] ?? 0;

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
            !$this->isEnglish($encadrant->specialite)
        ) {
            return "PFE {$pfe->id_pfe} non planifié : PFE en anglais sans encadrant anglais. "
                . "Aucun créneau valide n'a été trouvé avec les contraintes actuelles.";
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
                'id_salle' => $item['id_salle'],
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
            /* supprimer l'ancien planning */
            Soutenance::query()->delete();
            /* reinserer le nouveau planning*/
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
            'id_salle' => $slot['id_salle'],
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

    private function initialiserPfeMeta($pfes): array
    {
        $meta = [];

        foreach ($pfes as $pfe) {
            $meta[(int) $pfe->id_pfe] = [
                'filiere' => $this->filierePfe($pfe),
                'is_english' => $this->isEnglish($pfe->langue),
                'difficulty' => $this->scoreDifficultePfe($pfe),
            ];
        }

        return $meta;
    }

    private function initialiserProfMeta($professeurs): array
    {
        $meta = [];

        foreach ($professeurs as $prof) {
            $id = (int) $prof->id_professeur;

            $meta[$id] = [
                'is_info' => $this->isInfo($prof->specialite),
                'is_english' => $this->isEnglish($prof->specialite),
                'nom' => $this->nomComplet($prof),
                'specialite' => $prof->specialite ?? '-',
            ];
        }

        return $meta;
    }

    private function selectionnerPfesATesterPourSlot(
        $pfesRestants,
        array $pfeMeta,
        array $repartitionFilieres,
        string $date
    ) {
        return $pfesRestants
            ->sortBy(function ($pfe) use ($pfeMeta, $repartitionFilieres, $date) {

                $idPfe = (int) $pfe->id_pfe;

                $filiere = $pfeMeta[$idPfe]['filiere']
                    ?? $this->filierePfe($pfe);

                $difficulty = $pfeMeta[$idPfe]['difficulty']
                    ?? $this->scoreDifficultePfe($pfe);

                $filiereCountToday = $repartitionFilieres[$date][$filiere] ?? 0;

                /* plus le score est petit, plus le PFE est teste tot.
                  - difficulte elevee => priorite elevee
                  - filiere deja beaucoup utilisee ce jour => priorite plus faible */

                return ($filiereCountToday * 50) - $difficulty;
            })
            ->take(self::MAX_PFES_TESTES_PAR_SLOT);
    }

    private function profIsInfo($prof, array $profMeta): bool
    {
        $id = (int) $prof->id_professeur;

        return $profMeta[$id]['is_info']
            ?? $this->isInfo($prof->specialite);
    }

    private function profIsEnglish($prof, array $profMeta): bool
    {
        $id = (int) $prof->id_professeur;

        return $profMeta[$id]['is_english']
            ?? $this->isEnglish($prof->specialite);
    }

    private function anglaisPresentMeta($encadrant, $jury1, $jury2, array $profMeta): bool
    {
        foreach ([$encadrant, $jury1, $jury2] as $prof) {
            if ($prof && $this->profIsEnglish($prof, $profMeta)) {
                return true;
            }
        }

        return false;
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

    private function genererSlots(Carbon $dateDepart, array $salles, array $creneaux, int $maxExtraDays, ?Carbon $dateLimite = null): array
    {
        $slots = [];
        $date = $dateDepart->copy();
        $dateFin = $dateLimite ? $dateLimite->copy() : $dateDepart->copy()->addDays($maxExtraDays);

        while ($date->lte($dateFin)) {
            if ($date->dayOfWeek !== Carbon::SUNDAY) {
                foreach ($creneaux as $heure) {
                    foreach ($salles as $salle) {
                        $slots[] = [
                            'date' => $date->toDateString(),
                            'heure' => $heure,
                            'minute' => $this->minutesDepuisMinuit($heure),
                            'id_salle' => $salle['id_salle'],
                            'salle' => $salle['nom'],
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
            if ($salle instanceof Salle) {
                $idSalle = (int) $salle->id_salle;
                $nom = trim((string) $salle->nom);
            } elseif (is_array($salle)) {
                $idSalle = (int) ($salle['id_salle'] ?? $salle['id'] ?? 0);
                $nom = trim((string) ($salle['nom'] ?? ''));
            } elseif (is_numeric($salle)) {
                $model = Salle::find((int) $salle);
                $idSalle = $model ? (int) $model->id_salle : 0;
                $nom = $model ? trim((string) $model->nom) : '';
            } else {
                $nom = trim((string) $salle);
                $model = $nom !== '' ? Salle::where('nom', $nom)->first() : null;
                $idSalle = $model ? (int) $model->id_salle : 0;
                $nom = $model ? trim((string) $model->nom) : $nom;
            }

            if ($idSalle > 0 && $nom !== '') {
                $result[$idSalle] = [
                    'id_salle' => $idSalle,
                    'nom' => $nom,
                ];
            }
        }

        return array_values($result);
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

}
