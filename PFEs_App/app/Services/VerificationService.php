<?php

namespace App\Services;

use App\Models\Pfe;
use App\Models\Professeur;
use App\Models\Soutenance;
use Carbon\Carbon;

class VerificationService
{
    public function verifierAffectation(): array
    {
        $errors = [];
        $warnings = [];
        $checks = [];

        $totalPfes = Pfe::count();
        $totalProfesseurs = Professeur::count();

        if ($totalPfes === 0) {
            return $this->resultatErreur('Aucun PFE trouvé dans la base de données.');
        }

        if ($totalProfesseurs === 0) {
            return $this->resultatErreur('Aucun professeur trouvé dans la base de données.');
        }

        $pfes = Pfe::with(['etudiant', 'encadrant'])->get();
        $professeurs = Professeur::all();

        /*
        1. Tous les PFEs ont un encadrant
        */

        $pfesSansEncadrant = $pfes->filter(function ($pfe) {
            return $pfe->id_encadrant === null;
        });

        if ($pfesSansEncadrant->count() === 0) {
            $checks[] = $this->checkOk('Tous les PFEs ont un encadrant.');
        } else {
            $message = $pfesSansEncadrant->count() . ' PFE(s) sans encadrant.';

            $errors[] = $message;
            $checks[] = $this->checkFail($message);
        }

        /*
        2. nbr de PFEs affectes = nbr total de PFEs
        */

        $nbAffectes = $pfes->filter(function ($pfe) {
            return $pfe->id_encadrant !== null;
        })->count();

        if ($nbAffectes === $totalPfes) {
            $checks[] = $this->checkOk("Nombre de PFEs affectés correct : {$nbAffectes}/{$totalPfes}.");
        } else {
            $message = "Nombre de PFEs affectés incorrect : {$nbAffectes}/{$totalPfes}.";

            $errors[] = $message;
            $checks[] = $this->checkFail($message);
        }

        /*
        3. PFE anglais => encadrant anglais
        */

        $pfesAnglais = $pfes->filter(function ($pfe) {
            return $this->isEnglish($pfe->langue);
        });

        $professeursAnglais = $professeurs->filter(function ($professeur) {
            return $this->isEnglish($professeur->specialite);
        });

        $pfesAnglaisSansEncadrantAnglais = collect();

        if ($pfesAnglais->count() === 0) {
            $checks[] = $this->checkOk('Aucun PFE en anglais : vérification encadrant anglais non applicable.');
        } else {
            if ($professeursAnglais->count() === 0) {
                $message = 'Il existe des PFEs en anglais, mais aucun professeur de spécialité anglais.';

                $errors[] = $message;
                $checks[] = $this->checkFail($message);
            } else {
                $pfesAnglaisSansEncadrantAnglais = $pfesAnglais->filter(function ($pfe) {
                    return !$pfe->encadrant
                        || !$this->isEnglish($pfe->encadrant->specialite);
                });

                if ($pfesAnglaisSansEncadrantAnglais->count() === 0) {
                    $checks[] = $this->checkOk(
                        "Tous les PFEs anglais ont un encadrant de spécialité anglais : {$pfesAnglais->count()} PFE(s)."
                    );
                } else {
                    $message = $pfesAnglaisSansEncadrantAnglais->count()
                        . " PFE(s) anglais n'ont pas un encadrant de spécialité anglais. "
                        . "Les professeurs anglais peuvent être ajoutés comme jury pendant la planification.";                    $warnings[] = $message;
                    $checks[] = $this->checkWarning($message);
                }
            }
        }

        /*
        4. Répartition des encadrants
        */

        $repartition = [];

        foreach ($professeurs as $professeur) {
            $idProf = (int) $professeur->id_professeur;

            $charge = $pfes->filter(function ($pfe) use ($idProf) {
                return (int) $pfe->id_encadrant === $idProf;
            })->count();

            $repartition[] = [
                'id_professeur' => $idProf,
                'professeur' => trim($professeur->nom . ' ' . $professeur->prenom),
                'specialite' => $professeur->specialite,
                'nombre_pfes' => $charge,
            ];
        }

        $charges = collect($repartition)->pluck('nombre_pfes');

        $min = $charges->min();
        $max = $charges->max();
        $moyenne = round($charges->avg(), 2);
        $tolerance = max(1, (int) ceil($moyenne * 0.30));

        if (($max - $min) <= $tolerance) {
            $checks[] = $this->checkOk(
                "Répartition des encadrants raisonnablement équilibrée. Min={$min}, Max={$max}, Tolérance={$tolerance}."
            );
        } else {
            $message = "Répartition des encadrants un peu déséquilibrée : Min={$min}, Max={$max}, Tolérance={$tolerance}.";

            $warnings[] = $message;
            $checks[] = $this->checkWarning($message);
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'checks' => $checks,

            'stats' => [
                'total_pfes' => $totalPfes,
                'pfes_affectes' => $nbAffectes,
                'pfes_sans_encadrant' => $pfesSansEncadrant->count(),
                'pfes_anglais' => $pfesAnglais->count(),
                'professeurs' => $totalProfesseurs,
                'professeurs_anglais' => $professeursAnglais->count(),
                'min_charge' => $min,
                'max_charge' => $max,
                'tolerance' => $tolerance,
            ],

            'repartition_encadrants' => collect($repartition)
                ->sortByDesc('nombre_pfes')
                ->values()
                ->toArray(),

            'pfes_anglais_sans_encadrant_anglais' => $pfesAnglaisSansEncadrantAnglais
                ->map(function ($pfe) {
                    return [
                        'id_pfe' => $pfe->id_pfe,
                        'sujet' => $pfe->sujet,
                        'langue' => $pfe->langue,
                        'filiere' => $pfe->etudiant?->filiere ?? '-',
                        'encadrant' => $pfe->encadrant
                            ? trim($pfe->encadrant->nom . ' ' . $pfe->encadrant->prenom)
                            : 'Aucun encadrant',
                        'specialite_encadrant' => $pfe->encadrant?->specialite ?? '-',
                    ];
                })
                ->values()
                ->toArray(),
        ];
    }

    public function verifierPlanning(): array
    {
        $errors = [];
        $warnings = [];
        $checks = [];

        $creneaux = array_values(array_map(
            fn ($heure) => $this->normaliserHeure((string) $heure),
            session('creneaux', [])
        ));

        $dureeMinutes = (int) config('pfe.duree_soutenance_minutes', 60);

        if (empty($creneaux)) {
            return $this->resultatErreur('Aucun créneau selectionne.');
        }

        if ($dureeMinutes <= 0) {
            return $this->resultatErreur('La durée de soutenance dans config/pfe.php est invalide.');
        }

        $checks[] = $this->checkOk('Les créneaux et la durée sont valides.');

        $soutenances = Soutenance::with([
            'pfe.etudiant',
            'pfe.encadrant',
            'salle',
            'jury1',
            'jury2',
        ])->get();

        $totalPfes = Pfe::count();
        $totalSoutenances = $soutenances->count();
        $professeurs = Professeur::all();

        if ($totalSoutenances === 0) {
            return $this->resultatErreur("Aucune soutenance trouvée. Veuillez générer le planning d'abord.");
        }

        /*
        1. Tous les PFEs ont une soutenance
        */

        $idsPfePlanifies = $soutenances
            ->pluck('id_pfe')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $idsPfeUniques = array_unique($idsPfePlanifies);

        if (count($idsPfeUniques) === $totalPfes && $totalSoutenances === $totalPfes) {
            $checks[] = $this->checkOk("Tous les PFEs ont une soutenance : {$totalSoutenances}/{$totalPfes}.");
        } else {
            $message = "Nombre de soutenances incorrect ou PFEs dupliqués : {$totalSoutenances} soutenance(s) pour {$totalPfes} PFE(s).";

            $errors[] = $message;
            $checks[] = $this->checkFail($message);
        }

        /*
        2. Les heures doivent exister dans config/pfe.php
        */

        $soutenancesHorsConfig = [];

        foreach ($soutenances as $soutenance) {
            $heure = $this->normaliserHeure($soutenance->heure_debut);

            if (!in_array($heure, $creneaux, true)) {
                $soutenancesHorsConfig[] = $soutenance;
            }
        }

        if (empty($soutenancesHorsConfig)) {
            $checks[] = $this->checkOk('Toutes les heures de soutenance sont valides.');
        } else {
            $message = count($soutenancesHorsConfig) . ' soutenance(s) utilisent une heure absente de config/pfe.php.';

            $errors[] = $message;
            $checks[] = $this->checkFail($message);
        }

        /*
        3. Jury1 ≠ Jury2 ≠ Encadrant
        */

        $conflitsJurys = [];

        foreach ($soutenances as $soutenance) {
            $idEncadrant = (int) $soutenance->pfe?->id_encadrant;
            $idJury1 = (int) $soutenance->id_jury1;
            $idJury2 = (int) $soutenance->id_jury2;

            if (
                $idEncadrant === $idJury1 ||
                $idEncadrant === $idJury2 ||
                $idJury1 === $idJury2
            ) {
                $conflitsJurys[] = $soutenance;
            }
        }

        if (empty($conflitsJurys)) {
            $checks[] = $this->checkOk('Tous les jurys sont differents : Jury1 ≠ Jury2 ≠ Encadrant.');
        } else {
            $message = count($conflitsJurys) . ' soutenance(s) ont un conflit entre encadrant, jury1 et jury2.';

            $errors[] = $message;
            $checks[] = $this->checkFail($message);
        }

        /*
        4. Pas deux soutenances meme salle/date/heure
        */

        $salleCreneaux = [];
        $conflitsSalles = [];
        $soutenancesSansSalle = [];

        foreach ($soutenances as $soutenance) {
            $date = Carbon::parse($soutenance->date_soutenance)->toDateString();
            $heure = $this->normaliserHeure($soutenance->heure_debut);
            $idSalle = (int) $soutenance->id_salle;

            if ($idSalle <= 0 || !$soutenance->salle) {
                $soutenancesSansSalle[] = $soutenance;
                continue;
            }

            $key = $date . '|' . $heure . '|' . $idSalle;

            if (isset($salleCreneaux[$key])) {
                $conflitsSalles[] = $soutenance;
            }

            $salleCreneaux[$key] = true;
        }

        if (empty($soutenancesSansSalle) && empty($conflitsSalles)) {
            $checks[] = $this->checkOk("Aucune salle n'est utilisée deux fois dans la meme date et meme heure.");
        } else {
            if (!empty($soutenancesSansSalle)) {
                $message = count($soutenancesSansSalle) . ' soutenance(s) sans salle associée dans la base de données.';

                $errors[] = $message;
                $checks[] = $this->checkFail($message);
            }

            if (!empty($conflitsSalles)) {
                $message = count($conflitsSalles) . ' conflit(s) salle/date/heure détecté(s).';

                $errors[] = $message;
                $checks[] = $this->checkFail($message);
            }
        }

        /*
        5. Un professeur ne doit pas etre dans deux soutenances au meme horaire
        */

        $profCreneaux = [];
        $conflitsProfs = [];

        foreach ($soutenances as $soutenance) {
            $date = Carbon::parse($soutenance->date_soutenance)->toDateString();
            $heure = $this->normaliserHeure($soutenance->heure_debut);

            $idsProfs = $this->idsProfsSoutenance($soutenance);

            foreach ($idsProfs as $idProf) {
                $key = $date . '|' . $heure . '|' . $idProf;

                if (isset($profCreneaux[$key])) {
                    $conflitsProfs[] = [
                        'id_professeur' => $idProf,
                        'date' => $date,
                        'heure' => $heure,
                    ];
                }

                $profCreneaux[$key] = true;
            }
        }

        if (empty($conflitsProfs)) {
            $checks[] = $this->checkOk("Aucun professeur n'est programmé dans deux soutenances au meme horaire.");
        } else {
            $message = count($conflitsProfs) . ' conflit(s) professeur meme horaire détecté(s).';

            $errors[] = $message;
            $checks[] = $this->checkFail($message);
        }

        /*
        6. Pas deux soutenances consécutives pour un professeur
        */

        $soutenancesParProfJour = [];

        foreach ($soutenances as $soutenance) {
            $date = Carbon::parse($soutenance->date_soutenance)->toDateString();
            $heure = $this->normaliserHeure($soutenance->heure_debut);

            foreach ($this->idsProfsSoutenance($soutenance) as $idProf) {
                $soutenancesParProfJour[$idProf][$date][] = $heure;
            }
        }

        $conflitsConsecutifs = [];

        foreach ($soutenancesParProfJour as $idProf => $jours) {
            foreach ($jours as $date => $heures) {
                sort($heures);

                for ($i = 0; $i < count($heures); $i++) {
                    for ($j = $i + 1; $j < count($heures); $j++) {
                        $diff = abs(
                            $this->minutesDepuisMinuit($heures[$i]) -
                            $this->minutesDepuisMinuit($heures[$j])
                        );

                        if ($diff <= $dureeMinutes) {
                            $conflitsConsecutifs[] = [
                                'id_professeur' => $idProf,
                                'date' => $date,
                                'heure1' => $heures[$i],
                                'heure2' => $heures[$j],
                            ];
                        }
                    }
                }
            }
        }

        if (empty($conflitsConsecutifs)) {
            $checks[] = $this->checkOk("Aucun professeur n'a deux soutenances consécutives.");
        } else {
            $message = count($conflitsConsecutifs) . ' conflit(s) de soutenances consécutives détecté(s).';

            $errors[] = $message;
            $checks[] = $this->checkFail($message);
        }

        /*
        7. Pas de soutenance le dimanche
        */

        $soutenancesDimanche = $soutenances->filter(function ($soutenance) {
            return Carbon::parse($soutenance->date_soutenance)->isSunday();
        });

        if ($soutenancesDimanche->count() === 0) {
            $checks[] = $this->checkOk("Aucune soutenance n'est programmée le dimanche.");
        } else {
            $message = $soutenancesDimanche->count() . ' soutenance(s) programmée(s) le dimanche.';

            $errors[] = $message;
            $checks[] = $this->checkFail($message);
        }

        /*
        8. Chaque soutenance contient au moins 2 professeurs info
        */

        $soutenancesSansDeuxInfo = [];

        foreach ($soutenances as $soutenance) {
            $profs = [
                $soutenance->pfe?->encadrant,
                $soutenance->jury1,
                $soutenance->jury2,
            ];

            $countInfo = 0;

            foreach ($profs as $prof) {
                if ($prof && $this->isInfo($prof->specialite)) {
                    $countInfo++;
                }
            }

            if ($countInfo < 2) {
                $soutenancesSansDeuxInfo[] = $soutenance;
            }
        }

        if (empty($soutenancesSansDeuxInfo)) {
            $checks[] = $this->checkOk('Chaque soutenance contient au moins 2 professeurs de spécialité informatique.');
        } else {
            $message = count($soutenancesSansDeuxInfo) . " soutenance(s) n'ont pas 2 professeurs informatique.";

            $errors[] = $message;
            $checks[] = $this->checkFail($message);
        }

        /*
        9. PFE anglais avec prof anglais, sauf saturation
        */

        $chargesProfesseurs = $this->calculerChargesProfesseurs($soutenances, $professeurs);

        $professeursAnglais = $professeurs->filter(function ($professeur) {
            return $this->isEnglish($professeur->specialite);
        });

        $chargeMaxProf = (int) ceil(($totalSoutenances * 3) / max($professeurs->count(), 1));

        $pfesAnglaisSansProfAnglais = [];

        foreach ($soutenances as $soutenance) {
            $pfe = $soutenance->pfe;

            if (!$pfe || !$this->isEnglish($pfe->langue)) {
                continue;
            }

            $profs = [
                $pfe->encadrant,
                $soutenance->jury1,
                $soutenance->jury2,
            ];

            $hasEnglishProf = false;

            foreach ($profs as $prof) {
                if ($prof && $this->isEnglish($prof->specialite)) {
                    $hasEnglishProf = true;
                    break;
                }
            }

            if (!$hasEnglishProf) {
                $pfesAnglaisSansProfAnglais[] = $soutenance;
            }
        }

        if (empty($pfesAnglaisSansProfAnglais)) {
            $checks[] = $this->checkOk('Tous les PFEs anglais ont au moins un professeur de spécialité anglais.');
        } else {
            $tousAnglaisSatures = $this->tousProfsAnglaisSatures(
                $professeursAnglais,
                $chargesProfesseurs,
                $chargeMaxProf
            );

            if ($tousAnglaisSatures) {
                $message = count($pfesAnglaisSansProfAnglais)
                    . ' PFE(s) anglais sans professeur anglais, mais tous les professeurs anglais ont atteint leur charge équitable.';
            } else {
                $message = count($pfesAnglaisSansProfAnglais)
                    . ' PFE(s) anglais sans professeur anglais. meme si il(s) existe(ent) un/des prof(s) anglais non sature(s).';
            }

            $warnings[] = $message;
            $checks[] = $this->checkWarning($message);
        }

        /*
        10. Répartition équitable des filières sur les jours
        */

        $repartitionFilieres = $this->calculerRepartitionFilieres($soutenances);

        $warningFiliere = $this->verifierEquilibreFilieres($repartitionFilieres);

        if ($warningFiliere === null) {
            $checks[] = $this->checkOk('Répartition des filières sur les jours raisonnablement équilibrée.');
        } else {
            $warnings[] = $warningFiliere;
            $checks[] = $this->checkWarning($warningFiliere);
        }

        /*
        11. Répartition des professeurs sur les jours
        */

        $warningProfJour = $this->verifierEquilibreProfesseursParJour($soutenances, $professeurs);

        if ($warningProfJour === null) {
            $checks[] = $this->checkOk('Répartition des soutenances des professeurs sur les jours raisonnablement équilibrée.');
        } else {
            $warnings[] = $warningProfJour;
            $checks[] = $this->checkWarning($warningProfJour);
        }

        /*
        12. Répartition globale sur les professeurs
        */

        $warningProfGlobal = $this->verifierEquilibreProfesseursGlobal($chargesProfesseurs);

        if ($warningProfGlobal === null) {
            $checks[] = $this->checkOk('Répartition globale des soutenances sur les professeurs raisonnablement équilibrée.');
        } else {
            $warnings[] = $warningProfGlobal;
            $checks[] = $this->checkWarning($warningProfGlobal);
        }

        /*
        13. Éviter le même horaire répété pour un professeur
        */

        $warningHoraire = $this->verifierRepetitionHoraires($soutenances, $creneaux);

        if ($warningHoraire === null) {
            $checks[] = $this->checkOk("Aucun professeur n'est trop souvent placé au même horaire.");
        } else {
            $warnings[] = $warningHoraire;
            $checks[] = $this->checkWarning($warningHoraire);
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'checks' => $checks,

            'stats' => [
                'total_pfes' => $totalPfes,
                'total_soutenances' => $totalSoutenances,
                'conflits_jurys' => count($conflitsJurys),
                'conflits_salles' => count($conflitsSalles),
                'soutenances_sans_salle' => count($soutenancesSansSalle),
                'conflits_profs_meme_horaire' => count($conflitsProfs),
                'conflits_consecutifs' => count($conflitsConsecutifs),
                'soutenances_dimanche' => $soutenancesDimanche->count(),
                'soutenances_sans_deux_info' => count($soutenancesSansDeuxInfo),
                'pfes_anglais_sans_prof_anglais' => count($pfesAnglaisSansProfAnglais),
                'charge_max_prof' => $chargeMaxProf,
            ],

            'repartition_profs' => $this->calculerRepartitionProfesseurs($soutenances, $professeurs),
            'repartition_filieres' => $repartitionFilieres,
        ];
    }

    private function idsProfsSoutenance($soutenance): array
    {
        return [
            (int) $soutenance->pfe?->id_encadrant,
            (int) $soutenance->id_jury1,
            (int) $soutenance->id_jury2,
        ];
    }

    private function calculerChargesProfesseurs($soutenances, $professeurs): array
    {
        $charges = [];

        foreach ($professeurs as $professeur) {
            $charges[(int) $professeur->id_professeur] = 0;
        }

        foreach ($soutenances as $soutenance) {
            foreach ($this->idsProfsSoutenance($soutenance) as $idProf) {
                if (!isset($charges[$idProf])) {
                    $charges[$idProf] = 0;
                }

                $charges[$idProf]++;
            }
        }

        return $charges;
    }

    private function tousProfsAnglaisSatures($professeursAnglais, array $chargesProfesseurs, int $chargeMaxProf): bool
    {
        if ($professeursAnglais->isEmpty()) {
            return false;
        }

        foreach ($professeursAnglais as $professeur) {
            $idProf = (int) $professeur->id_professeur;

            if (($chargesProfesseurs[$idProf] ?? 0) < $chargeMaxProf) {
                return false;
            }
        }

        return true;
    }

    private function calculerRepartitionProfesseurs($soutenances, $professeurs): array
    {
        $stats = [];

        foreach ($professeurs as $professeur) {
            $stats[(int) $professeur->id_professeur] = [
                'professeur' => trim($professeur->nom . ' ' . $professeur->prenom),
                'specialite' => $professeur->specialite,
                'encadrant' => 0,
                'jury1' => 0,
                'jury2' => 0,
                'total' => 0,
            ];
        }

        foreach ($soutenances as $soutenance) {
            $idEncadrant = (int) $soutenance->pfe?->id_encadrant;
            $idJury1 = (int) $soutenance->id_jury1;
            $idJury2 = (int) $soutenance->id_jury2;

            if (isset($stats[$idEncadrant])) {
                $stats[$idEncadrant]['encadrant']++;
                $stats[$idEncadrant]['total']++;
            }

            if (isset($stats[$idJury1])) {
                $stats[$idJury1]['jury1']++;
                $stats[$idJury1]['total']++;
            }

            if (isset($stats[$idJury2])) {
                $stats[$idJury2]['jury2']++;
                $stats[$idJury2]['total']++;
            }
        }

        return collect($stats)
            ->sortByDesc('total')
            ->values()
            ->toArray();
    }

    private function calculerRepartitionFilieres($soutenances): array
    {
        $stats = [];

        foreach ($soutenances as $soutenance) {
            $date = Carbon::parse($soutenance->date_soutenance)->toDateString();
            $filiere = strtoupper(trim((string) ($soutenance->pfe?->etudiant?->filiere ?? 'NON_DEFINIE')));

            $stats[$date][$filiere] = ($stats[$date][$filiere] ?? 0) + 1;
        }

        ksort($stats);

        return $stats;
    }

    private function verifierEquilibreFilieres(array $repartitionFilieres): ?string
    {
        if (empty($repartitionFilieres)) {
            return null;
        }

        $filieres = [];

        foreach ($repartitionFilieres as $date => $items) {
            foreach ($items as $filiere => $count) {
                $filieres[$filiere] = true;
            }
        }

        foreach (array_keys($filieres) as $filiere) {
            $counts = [];

            foreach ($repartitionFilieres as $date => $items) {
                $counts[] = $items[$filiere] ?? 0;
            }

            if ((max($counts) - min($counts)) > 2) {
                return "Répartition des filières à surveiller : la filière {$filiere} est déséquilibrée sur les jours.";
            }
        }

        return null;
    }

    private function verifierEquilibreProfesseursParJour($soutenances, $professeurs): ?string
    {
        $dates = $soutenances
            ->pluck('date_soutenance')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values()
            ->toArray();

        if (empty($dates)) {
            return null;
        }

        $stats = [];

        foreach ($professeurs as $professeur) {
            $idProf = (int) $professeur->id_professeur;

            foreach ($dates as $date) {
                $stats[$idProf][$date] = 0;
            }
        }

        foreach ($soutenances as $soutenance) {
            $date = Carbon::parse($soutenance->date_soutenance)->toDateString();

            foreach ($this->idsProfsSoutenance($soutenance) as $idProf) {
                if (!isset($stats[$idProf][$date])) {
                    $stats[$idProf][$date] = 0;
                }

                $stats[$idProf][$date]++;
            }
        }

        foreach ($stats as $idProf => $jours) {
            $total = array_sum($jours);

            if ($total === 0) {
                continue;
            }

            $moyenne = $total / max(count($dates), 1);
            $tolerance = max(1, (int) ceil($moyenne));

            if ((max($jours) - min($jours)) > ($tolerance + 1)) {
                return "Répartition par jour à surveiller : le professeur ID {$idProf} a une charge déséquilibrée selon les jours.";
            }
        }

        return null;
    }

    private function verifierEquilibreProfesseursGlobal(array $chargesProfesseurs): ?string
    {
        if (empty($chargesProfesseurs)) {
            return null;
        }

        $charges = array_values($chargesProfesseurs);

        $min = min($charges);
        $max = max($charges);
        $moyenne = array_sum($charges) / max(count($charges), 1);
        $tolerance = max(1, (int) ceil($moyenne * 0.30));

        if (($max - $min) > $tolerance + 1) {
            return "Répartition globale des professeurs à surveiller : Min={$min}, Max={$max}.";
        }

        return null;
    }

    private function verifierRepetitionHoraires($soutenances, array $creneaux): ?string
    {
        $stats = [];

        foreach ($soutenances as $soutenance) {
            $heure = $this->normaliserHeure($soutenance->heure_debut);

            foreach ($this->idsProfsSoutenance($soutenance) as $idProf) {
                $stats[$idProf][$heure] = ($stats[$idProf][$heure] ?? 0) + 1;
            }
        }

        foreach ($stats as $idProf => $heures) {
            $total = array_sum($heures);

            if ($total <= 1) {
                continue;
            }

            $moyenne = $total / max(count($creneaux), 1);
            $limite = max(2, (int) ceil($moyenne + 2));

            foreach ($heures as $heure => $count) {
                if ($count > $limite) {
                    return "Répétition horaire à surveiller : le professeur ID {$idProf} est programmé {$count} fois à {$heure}.";
                }
            }
        }

        return null;
    }

    private function isEnglish(?string $text): bool
    {
        if (!$text) {
            return false;
        }

        $text = strtolower(trim($text));

        return in_array($text, ['anglais', 'english', 'eng', 'en'], true)
            || str_contains($text, 'anglais')
            || str_contains($text, 'english');
    }

    private function isInfo(?string $text): bool
    {
        if (!$text) {
            return false;
        }

        $text = strtolower(trim($text));

        return str_contains($text, 'informatique')
            || str_contains($text, 'info')
            || str_contains($text, 'computer')
            || str_contains($text, 'software')
            || $text === 'cs';
    }

    private function minutesDepuisMinuit(string $heure): int
    {
        $heure = $this->normaliserHeure($heure);
        $parts = explode(':', $heure);

        return ((int) $parts[0]) * 60 + ((int) $parts[1]);
    }

    private function normaliserHeure(string $heure): string
    {
        $parts = explode(':', trim($heure));

        $hour = (int) ($parts[0] ?? 0);
        $minute = (int) ($parts[1] ?? 0);

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function checkOk(string $message): array
    {
        return [
            'status' => 'ok',
            'message' => $message,
        ];
    }

    private function checkFail(string $message): array
    {
        return [
            'status' => 'error',
            'message' => $message,
        ];
    }

    private function checkWarning(string $message): array
    {
        return [
            'status' => 'warning',
            'message' => $message,
        ];
    }

    private function resultatErreur(string $message): array
    {
        return [
            'is_valid' => false,
            'errors' => [$message],
            'warnings' => [],
            'checks' => [
                [
                    'status' => 'error',
                    'message' => $message,
                ],
            ],
            'stats' => [],
            'repartition_encadrants' => [],
            'repartition_profs' => [],
            'repartition_filieres' => [],
            'pfes_anglais_sans_encadrant_anglais' => [],
        ];
    }
}
