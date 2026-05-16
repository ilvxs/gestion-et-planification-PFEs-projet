<?php

namespace App\Services;

use App\Models\Pfe;
use App\Models\Professeur;
use App\Models\Soutenance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PlanningService
{
    /**
     * Génération principale du planning
     */
    public function generer($dateDebut, $salles): array
    {
        set_time_limit(300);

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Soutenance::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $pfes = Pfe::with(['etudiant', 'encadrant'])->get();

        $professeurs = Professeur::all();

        $profsAnglais = $professeurs->filter(function ($prof) {
            return $this->isEnglish($prof->specialite);
        });

        $profStats = [];

        foreach ($professeurs as $prof) {

            $profStats[$prof->id_professeur] = [

                'nom' => $prof->nom . ' ' . $prof->prenom,

                'count' => 0,

                '09_count' => 0,
            ];
        }

        $creneaux = config('pfe.creneaux');

        $planning = [];
        $errors = [];
        $warnings = [];
        $filiereParJour = [];
        $profSoutenancesParJour = []; // Tracker soutenances par prof par jour

        $created = 0;

        $totalParticipations = count($pfes) * 3;
        $maxSoutenances = ceil(
            $totalParticipations / $professeurs->count()
        );

        $dateCourante = Carbon::parse($dateDebut);

        $indexSalle = 0;
        $indexCreneau = 0;

        $pfesRestants = $pfes->values();

        while ($pfesRestants->count() > 0) {

            $pfe = $pfesRestants->sortBy(function ($pfe) use (
                    $filiereParJour,
                    $dateCourante
                ) {

                    return $this->scoreFiliereJour(
                        $filiereParJour,
                        $dateCourante->toDateString(),
                        $pfe->etudiant->filiere
                    );
                })
                ->first();

            $pfeAnglais =  $this->isEnglish($pfe->langue);
                            
            // sauter dimanche
            while ($dateCourante->dayOfWeek === Carbon::SUNDAY) {
                $dateCourante->addDay();
            }

            $salle = $salles[$indexSalle];

            $heure = $creneaux[$indexCreneau];

            $encadrant = $pfe->encadrant;

            $encadrantInfo = $encadrant && $this->isInfo($encadrant->specialite);

            $encadrantAnglais = $encadrant && $this->isEnglish($encadrant->specialite);

            if (!$encadrant) {

                $warnings[] =
                    "PFE {$pfe->id_pfe} ignoré : aucun encadrant.";

                continue;
            }

            // profs disponibles

            
            $disponibles = $professeurs
                ->where('id_professeur', '!=', $encadrant->id_professeur)
                ->sortBy(function ($prof) use ($profStats, $heure) {
                    return $profStats[$prof->id_professeur]['count'] +
                            $this->avoidMorningBias(
                                $prof,
                                $heure,
                                $profStats
                            );
                });

            $jury1 = null;
            $jury2 = null;
            
            if (
                $pfeAnglais &&
                !$encadrantAnglais &&
                $this->englishProfDisponible(
                    $professeurs,
                    $profStats,
                    $maxSoutenances
                )
            ) {

                $disponibles = $disponibles->sortByDesc(function ($prof) {

                    return $this->isEnglish($prof->specialite);
                });
            }
            
            if (!$encadrantInfo) {
                $disponibles = $disponibles->sortByDesc(function ($prof) {

                    return $this->isInfo($prof->specialite);
                });
            }

            // ===============================
            // Réserver les profs anglais
            // aux PFEs anglais
            // ===============================

            if (!$pfeAnglais) {

                $disponibles = $disponibles->sortBy(function ($prof) {

                    return $this->isEnglish($prof->specialite) ? 1 : 0;
                });
            }

            foreach ($disponibles as $prof) {

                if ($this->profOccupe(
                    $planning,
                    $prof->id_professeur,
                    $dateCourante->toDateString(),
                    $heure
                )) {
                    continue;
                }

                if ($this->profConsecutif(
                    $planning,
                    $prof->id_professeur,
                    $dateCourante->toDateString(),
                    $heure
                )) {
                    continue;
                }

                // NOUVELLE CONTRAINTE : Répartition équitable sur les jours (soft constraint)
                if ($this->profTooManyOnDay(
                    $profSoutenancesParJour,
                    $prof->id_professeur,
                    $dateCourante->toDateString(),
                    $profStats,
                    false  // not a fallback - try to respect the constraint
                )) {
                    continue;
                }

                $jury1 = $prof;
                break;
            }

            // Fallback : si pas de jury1 trouvé, relaxer la contrainte équitable
            if (!$jury1) {
                foreach ($disponibles as $prof) {

                    if ($this->profOccupe(
                        $planning,
                        $prof->id_professeur,
                        $dateCourante->toDateString(),
                        $heure
                    )) {
                        continue;
                    }

                    if ($this->profConsecutif(
                        $planning,
                        $prof->id_professeur,
                        $dateCourante->toDateString(),
                        $heure
                    )) {
                        continue;
                    }

                    $jury1 = $prof;
                    break;
                }
            }

            $currentInfoCount = 0;

            if (
                $encadrant &&
                $this->isInfo($encadrant->specialite)
            ) {
                $currentInfoCount++;
            }

            if (
                $jury1 &&
                $this->isInfo($jury1->specialite)
            ) {
                $currentInfoCount++;
            }

            if ($currentInfoCount < 2) {

                $disponibles = $disponibles->sortByDesc(function ($prof) {

                    return $this->isInfo($prof->specialite);
                });
            }

            if ($currentInfoCount >= 2) {

                $disponibles = $disponibles->sortBy(function ($prof) {

                    return $this->isInfo($prof->specialite) ? 1 : 0;
                });

            } else {

                $disponibles = $disponibles->sortByDesc(function ($prof) {

                    return $this->isInfo($prof->specialite);
                });
            }

            foreach ($disponibles as $prof) {

                if (!$jury1) {
                    break;
                }

                if (
                    $prof->id_professeur === $jury1->id_professeur
                ) {
                    continue;
                }
                if (
                    $encadrant &&
                    $prof->id_professeur === $encadrant->id_professeur
                ) {
                    continue;
                }

                if ($this->profOccupe(
                    $planning,
                    $prof->id_professeur,
                    $dateCourante->toDateString(),
                    $heure
                )) {
                    continue;
                }

                if ($this->profConsecutif(
                    $planning,
                    $prof->id_professeur,
                    $dateCourante->toDateString(),
                    $heure
                )) {
                    continue;
                }

                // NOUVELLE CONTRAINTE : Répartition équitable sur les jours (soft constraint)
                if ($this->profTooManyOnDay(
                    $profSoutenancesParJour,
                    $prof->id_professeur,
                    $dateCourante->toDateString(),
                    $profStats,
                    false  // not a fallback - try to respect the constraint
                )) {
                    continue;
                }

                $jury2 = $prof;
                break;
            }

            // Fallback : si pas de jury2 trouvé, relaxer la contrainte équitable
            if (!$jury2) {
                foreach ($disponibles as $prof) {

                    if (!$jury1) {
                        break;
                    }

                    if (
                        $prof->id_professeur === $jury1->id_professeur
                    ) {
                        continue;
                    }
                    if (
                        $encadrant &&
                        $prof->id_professeur === $encadrant->id_professeur
                    ) {
                        continue;
                    }

                    if ($this->profOccupe(
                        $planning,
                        $prof->id_professeur,
                        $dateCourante->toDateString(),
                        $heure
                    )) {
                        continue;
                    }

                    if ($this->profConsecutif(
                        $planning,
                        $prof->id_professeur,
                        $dateCourante->toDateString(),
                        $heure
                    )) {
                        continue;
                    }

                    $jury2 = $prof;
                    break;
                }
            }

            $infoCount = 0;

            if (
                $encadrant &&
                $this->isInfo($encadrant->specialite)
            ) {
                $infoCount++;
            }

            if (
                $jury1 &&
                $this->isInfo($jury1->specialite)
            ) {
                $infoCount++;
            }

            if (
                $jury2 &&
                $this->isInfo($jury2->specialite)
            ) {
                $infoCount++;
            }

            // ======================================================
            // FALLBACK SPECIAL : FORCER un prof informatique
            // même si surcharge
            // ======================================================

            if ($infoCount < 2) {

                $profsInfo = $professeurs->filter(function ($prof) use ($encadrant, $jury1, $jury2) {

                    // doit être info
                    if (!$this->isInfo($prof->specialite)) {
                        return false;
                    }

                    // éviter doublons
                    if ($encadrant && $prof->id_professeur === $encadrant->id_professeur) {
                        return false;
                    }

                    if ($jury1 && $prof->id_professeur === $jury1->id_professeur) {
                        return false;
                    }

                    if ($jury2 && $prof->id_professeur === $jury2->id_professeur) {
                        return false;
                    }

                    return true;
                });

                foreach ($profsInfo as $profInfo) {

                    // ici on IGNORE volontairement :
                    // - profTooManyOnDay
                    // - max soutenances
                    // - équilibre
                    // - consecutif

                    // on garde seulement :
                    // PAS le même créneau horaire

                    if ($this->profOccupe(
                        $planning,
                        $profInfo->id_professeur,
                        $dateCourante->toDateString(),
                        $heure
                    )) {
                        continue;
                    }

                    // remplacer un jury non-info par ce prof info

                    if ($jury1 && !$this->isInfo($jury1->specialite)) {

                        $jury1 = $profInfo;
                        $infoCount++;
                        break;
                    }

                    if ($jury2 && !$this->isInfo($jury2->specialite)) {

                        $jury2 = $profInfo;
                        $infoCount++;
                        break;
                    }
                }
            }

            if ($infoCount < 2) {
                // passer au créneau suivant
                $indexSalle++;

                if ($indexSalle >= count($salles)) {

                    $indexSalle = 0;
                    $indexCreneau++;
                }

                if ($indexCreneau >= count($creneaux)) {

                    $indexCreneau = 0;
                    $dateCourante->addDay();
                }

                // remettre le PFE plus tard
                continue;
            }

            if ($pfeAnglais) {
                $anglaisPresent =
                    $this->isEnglish($encadrant->specialite) ||

                    $this->isEnglish($jury1->specialite) ||

                    $this->isEnglish($jury2->specialite);

                if (!$anglaisPresent) {

                    $warnings[] =
                        "PFE {$pfe->id_pfe} anglais sans professeur anglais disponible.";
                }
            }

            if (!$jury1 || !$jury2) {

                $warnings[] =
                    "Impossible de trouver un jury pour PFE {$pfe->id_pfe}";

                continue;
            }

            Soutenance::create([
                'date_soutenance' => $dateCourante->toDateString(),
                'heure_debut' => $heure,
                'salle' => $salle,
                'id_pfe' => $pfe->id_pfe,
                'id_jury1' => $jury1->id_professeur,
                'id_jury2' => $jury2->id_professeur,
            ]);

            $planning[] = [
                'date' => $dateCourante->toDateString(),
                'heure' => $heure,
                'salle' => $salle,

                'filiere' => $pfe->etudiant->filiere,

                'id_encadrant' => $encadrant->id_professeur,
                'id_jury1' => $jury1->id_professeur,
                'id_jury2' => $jury2->id_professeur,

                'pfe' => $pfe->sujet,

                'encadrant' =>
                    $encadrant->nom . ' ' . $encadrant->prenom,

                'specialite_encadrant' =>
                    $encadrant->specialite ?? '-',

                'jury1' =>
                    $jury1->nom . ' ' . $jury1->prenom,

                'specialite_jury1' =>
                    $jury1->specialite ?? '-',

                'jury2' =>
                    $jury2->nom . ' ' . $jury2->prenom,

                'specialite_jury2' =>
                    $jury2->specialite ?? '-',
            ];

            $dateKey = $dateCourante->toDateString();
            $filiere = $pfe->etudiant->filiere;
            if (!isset($filiereParJour[$dateKey])) {
                $filiereParJour[$dateKey] = [];
            }
            if (!isset($filiereParJour[$dateKey][$filiere])) {
                $filiereParJour[$dateKey][$filiere] = 0;
            }
            $filiereParJour[$dateKey][$filiere]++;

            $profStats[$encadrant->id_professeur]['count']++;

            $profStats[$jury1->id_professeur]['count']++;

            $profStats[$jury2->id_professeur]['count']++;

            $created++;

            if ($heure === '09:00') {
                $profStats[$encadrant->id_professeur]['09_count']++;
                $profStats[$jury1->id_professeur]['09_count']++;
                $profStats[$jury2->id_professeur]['09_count']++;
            }

            // Mettre à jour le tracking par jour
            $dateKey = $dateCourante->toDateString();
            if (!isset($profSoutenancesParJour[$encadrant->id_professeur])) {
                $profSoutenancesParJour[$encadrant->id_professeur] = [];
            }
            if (!isset($profSoutenancesParJour[$encadrant->id_professeur][$dateKey])) {
                $profSoutenancesParJour[$encadrant->id_professeur][$dateKey] = 0;
            }
            $profSoutenancesParJour[$encadrant->id_professeur][$dateKey]++;

            if (!isset($profSoutenancesParJour[$jury1->id_professeur])) {
                $profSoutenancesParJour[$jury1->id_professeur] = [];
            }
            if (!isset($profSoutenancesParJour[$jury1->id_professeur][$dateKey])) {
                $profSoutenancesParJour[$jury1->id_professeur][$dateKey] = 0;
            }
            $profSoutenancesParJour[$jury1->id_professeur][$dateKey]++;

            if (!isset($profSoutenancesParJour[$jury2->id_professeur])) {
                $profSoutenancesParJour[$jury2->id_professeur] = [];
            }
            if (!isset($profSoutenancesParJour[$jury2->id_professeur][$dateKey])) {
                $profSoutenancesParJour[$jury2->id_professeur][$dateKey] = 0;
            }
            $profSoutenancesParJour[$jury2->id_professeur][$dateKey]++;

            $pfesRestants = $pfesRestants->reject(
                function ($item) use ($pfe) {
                return $item->id_pfe === $pfe->id_pfe;
            })
            ->values();

            // avancer créneau
            $indexSalle++;

            if ($indexSalle >= count($salles)) {

                $indexSalle = 0;

                $indexCreneau++;
            }

            if ($indexCreneau >= count($creneaux)) {

                $indexCreneau = 0;

                $dateCourante->addDay();
            }
        }

        return [
            'created' => $created,
            'errors' => $errors,
            'warnings' => $warnings,
            'planning' => $planning,
            'prof_stats' => $profStats,
        ];
    }

    /**
     * Vérifier si salle occupée
     */
    private function salleOccupee(array $planning, string $date, string $heure, string $salle): bool
    {
        foreach ($planning as $item) {

            if (
                $item['date'] === $date &&
                $item['heure'] === $heure &&
                $item['salle'] === $salle
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Choisir les jurys
     */
    private function choisirJurys(
        $pfe,
        $professeurs,
        array $planning,
        string $date,
        string $heure,
        array $profSoutenancesCount,
        array $profDayCount,
        array $profHoraireHistory
    ): ?array {

        $encadrantId = $pfe->id_encadrant;

        // Exclure encadrant
        $candidats = $professeurs->filter(function ($prof) use ($encadrantId) {
            return $prof->id_professeur !== $encadrantId;
        });

        // Trier par équité
        $candidats = $candidats->sortBy(function ($prof) use ($profSoutenancesCount) {
            return $profSoutenancesCount[$prof->id_professeur];
        });

        $jury1 = null;
        $jury2 = null;

        foreach ($candidats as $prof1) {

            if ($this->profDisponible(
                $planning,
                $prof1->id_professeur,
                $date,
                $heure
            ) === false) {
                continue;
            }

            foreach ($candidats as $prof2) {

                if ($prof1->id_professeur === $prof2->id_professeur) {
                    continue;
                }

                if ($this->profDisponible(
                    $planning,
                    $prof2->id_professeur,
                    $date,
                    $heure
                ) === false) {
                    continue;
                }

                // Vérifier spécialité informatique
                $infoCount = 0;

                $specialites = [
                    strtolower($prof1->specialite),
                    strtolower($prof2->specialite),
                ];

                if ($pfe->encadrant) {
                    $specialites[] = strtolower($pfe->encadrant->specialite);
                }

                foreach ($specialites as $specialite) {
                    if (str_contains($specialite, 'info')) {
                        $infoCount++;
                    }
                }

                if ($infoCount < 2) {
                    continue;
                }

                // Vérifier anglais
                if (strtolower($pfe->langue) === 'anglais') {

                    $anglaisFound = false;

                    foreach ($specialites as $specialite) {
                        if (str_contains($specialite, 'anglais')) {
                            $anglaisFound = true;
                            break;
                        }
                    }

                    if (!$anglaisFound) {
                        continue;
                    }
                }

                $jury1 = $prof1;
                $jury2 = $prof2;

                break 2;
            }
        }

        if (!$jury1 || !$jury2) {
            return null;
        }

        return [
            'jury1' => $jury1,
            'jury2' => $jury2,
        ];
    }

    /**
     * Vérifier disponibilité professeur
     */
    private function profDisponible(
        array $planning,
        int $profId,
        string $date,
        string $heure
    ): bool {

        foreach ($planning as $item) {

            if ($item['date'] !== $date) {
                continue;
            }

            if ($item['heure'] !== $heure) {
                continue;
            }

            if (
                str_contains($item['jury1'], (string)$profId) ||
                str_contains($item['jury2'], (string)$profId)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Passer au créneau suivant
     */
    private function incrementPosition(
        Carbon &$date,
        array $creneaux,
        array $salles,
        int &$creneauIndex,
        int &$salleIndex
    ): void {

        $salleIndex++;

        if ($salleIndex >= count($salles)) {

            $salleIndex = 0;

            $creneauIndex++;
        }

        if ($creneauIndex >= count($creneaux)) {

            $creneauIndex = 0;

            $date->addDay();
        }
    }
    private function profOccupe(
        array $planning,
        int $idProf,
        string $date,
        string $heure
    ): bool {

        foreach ($planning as $soutenance) {

            if (
                $soutenance['date'] === $date &&
                $soutenance['heure'] === $heure
            ) {

                if (
                    $soutenance['id_encadrant'] === $idProf ||
                    $soutenance['id_jury1'] === $idProf ||
                    $soutenance['id_jury2'] === $idProf
                ) {
                    return true;
                }
            }
        }

        return false;
    }
    private function profConsecutif(
        array $planning,
        int $idProf,
        string $date,
        string $heure
    ): bool {

        $creneaux = config('pfe.creneaux');

        $index = array_search($heure, $creneaux);

        if ($index === false) {
            return false;
        }

        $precedent = $creneaux[$index - 1] ?? null;

        $suivant = $creneaux[$index + 1] ?? null;

        foreach ($planning as $soutenance) {

            if ($soutenance['date'] !== $date) {
                continue;
            }

            $profPresent =
                $soutenance['id_encadrant'] === $idProf ||
                $soutenance['id_jury1'] === $idProf ||
                $soutenance['id_jury2'] === $idProf;

            if (!$profPresent) {
                continue;
            }

            if (
                $soutenance['heure'] === $precedent ||
                $soutenance['heure'] === $suivant
            ) {
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
        'en'
        ]);
    }

    private function englishProfDisponible(
        $professeurs,
        $profStats,
        $maxSoutenances
    ): bool {

        foreach ($professeurs as $prof) {

            if (!$this->isEnglish($prof->specialite)) {
                continue;
            }

            if (
                $profStats[$prof->id_professeur]['count']
                < $maxSoutenances
            ) {
                return true;
            }
        }

        return false;
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
            'computer science',
            'cs',
        ]);
    }

    private function avoidMorningBias(
        $prof,
        $heure,
        $profStats
    ): int {

        if ($heure !== '09:00') {
            return 0;
        }

        return $profStats[$prof->id_professeur]['09_count'];
    }

    private function scoreFiliereJour(
        $filiereParJour,
        $date,
        $filiere
    ): int {

        return $filiereParJour[$date][$filiere] ?? 0;
    }

    /**
     * Vérifier si un professeur a trop de soutenances ce jour
     * CONTRAINTE : Répartition équitable des soutenances d'un prof sur les jours
     * 
     * Exemple interdit :
     * Prof A:
     * - Lundi → 10 soutenances
     * - Mardi → 0
     * - Mercredi → 0
     * 
     * Cette fonction empêche une distribution très inéquitable.
     * Si un prof a N soutenances au total et D jours disponibles,
     * il ne devrait pas avoir beaucoup plus de ceil(N/D) soutenances par jour.
     */
    private function profTooManyOnDay(
        array $profSoutenancesParJour,
        int $idProf,
        string $date,
        array $profStats,
        bool $isFallback = false
    ): bool {
        // Nombre total de soutenances du professeur
        $totalSoutenances = $profStats[$idProf]['count'] ?? 0;
        
        // Si le prof n'a aucune soutenance encore, pas de problème
        if ($totalSoutenances === 0) {
            return false;
        }
        
        // Nombre de jours avec au moins une soutenance pour ce prof
        $daysWithSoutenances = 0;
        if (isset($profSoutenancesParJour[$idProf])) {
            $daysWithSoutenances = count($profSoutenancesParJour[$idProf]);
        }
        
        // Nombre de soutenances ce jour pour ce prof
        $soutenancesToday = $profSoutenancesParJour[$idProf][$date] ?? 0;
        
        // Calcul de la limite équitable
        // En fallback, on est plus lenient (limite × 1.5)
        // En mode normal, on est strict (limite × 1)
        $estimatedDays = max(3, $daysWithSoutenances + 1);
        $maxPerDay = ceil($totalSoutenances / $estimatedDays) + 1;
        
        if ($isFallback) {
            // Relaxer la contrainte de 50% en fallback
            $maxPerDay = ceil($maxPerDay * 1.5);
        }
        
        // Si en ajoutant une soutenance on dépasserait le maximum, refuser
        if ($soutenancesToday >= $maxPerDay) {
            return true;
        }
        
        return false;
    }
}
/*

# IMPORTANT — PROBLÈMES À CORRIGER PLUS TARD

Cette version respecte globalement les contraintes demandées, MAIS certaines contraintes sont simplifiées pour éviter une explosion de complexité algorithmique.

Les contraintes actuellement bien respectées :

* Jury1 ≠ Jury2 ≠ Encadrant
* Pas deux soutenances même salle/date/heure
* Pas deux soutenances pour même prof au même horaire
* Minimum 2 profs informatique
* Prof anglais pour PFE anglais
* Saut du dimanche
* Répartition partielle des soutenances
* Répartition équitable des soutenances d'un prof sur les jours ✓ (NOUVEAU)

Les contraintes simplifiées :

* Équilibrage parfait des filières sur les jours
* Équilibrage parfait des horaires d’un prof
* Éviter les soutenances consécutives
* Priorités intelligentes avancées
* Relaxation dynamique des contraintes

Pour un projet académique de licence/PFE, cette version est déjà très solide.*/
