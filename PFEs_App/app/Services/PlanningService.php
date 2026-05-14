<?php

namespace App\Services;

use App\Models\Pfe;
use App\Models\Professeur;
use App\Models\Soutenance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PlanningService
{
    private const MAX_EXTRA_DAYS = 10;

    public function generer(string $dateDebut, array $salles): array
    {
        $creneaux = config('pfe.creneaux', []);
        $salles = array_values(array_filter($salles));

        if (empty($creneaux)) {
            return [
                'created' => 0,
                'errors' => ['Aucun créneau trouvé dans config/pfe.php.'],
                'warnings' => [],
                'planning' => [],
            ];
        }

        if (empty($salles)) {
            return [
                'created' => 0,
                'errors' => ['Aucune salle trouvée dans la session.'],
                'warnings' => [],
                'planning' => [],
            ];
        }

        $totalPfes = Pfe::count();

        if ($totalPfes === 0) {
            return [
                'created' => 0,
                'errors' => ['Aucun PFE trouvé.'],
                'warnings' => [],
                'planning' => [],
            ];
        }

        $pfesSansEncadrant = Pfe::whereNull('id_encadrant')->count();

        if ($pfesSansEncadrant > 0) {
            return [
                'created' => 0,
                'errors' => [
                    "Impossible de générer le planning : {$pfesSansEncadrant} PFE(s) n'ont pas encore d'encadrant."
                ],
                'warnings' => [],
                'planning' => [],
            ];
        }

        $professeurs = Professeur::all();

        if ($professeurs->count() < 3) {
            return [
                'created' => 0,
                'errors' => ['Il faut au minimum 3 professeurs pour former Encadrant + Jury1 + Jury2.'],
                'warnings' => [],
                'planning' => [],
            ];
        }

        $pfes = Pfe::with('etudiant')
            ->whereNotNull('id_encadrant')
            ->get();

        $profsById = $professeurs->keyBy('id_professeur');

        $capaciteParJour = count($salles) * count($creneaux);
        $nombreJoursMin = (int) ceil($totalPfes / $capaciteParJour);

        // On traite les PFEs anglais en premier car ils sont plus difficiles à placer.
        $pfesTries = $pfes->sortBy(function ($pfe) {
            return $this->isPfeAnglais($pfe) ? 0 : 1;
        })->values();

        for ($nombreJours = $nombreJoursMin; $nombreJours <= $nombreJoursMin + self::MAX_EXTRA_DAYS; $nombreJours++) {
            $dates = $this->genererDates($dateDebut, $nombreJours);

            $etat = $this->initialiserEtat($professeurs, $totalPfes);

            $planning = [];
            $warnings = [];
            $success = true;
            $pfeNonPlace = null;

            foreach ($pfesTries as $pfe) {
                $pfeAnglais = $this->isPfeAnglais($pfe);

                // Premier essai : pour PFE anglais, essayer avec un prof anglais disponible équitablement.
                $combinaison = $this->chercherMeilleureCombinaison(
                    $pfe,
                    $professeurs,
                    $profsById,
                    $dates,
                    $creneaux,
                    $salles,
                    $etat,
                    $pfeAnglais
                );

                // Deuxième essai : si impossible, on relâche la contrainte anglais.
                if (!$combinaison && $pfeAnglais) {
                    $combinaison = $this->chercherMeilleureCombinaison(
                        $pfe,
                        $professeurs,
                        $profsById,
                        $dates,
                        $creneaux,
                        $salles,
                        $etat,
                        false
                    );

                    if ($combinaison) {
                        $idsProfs = [
                            $pfe->id_encadrant,
                            $combinaison['id_jury1'],
                            $combinaison['id_jury2'],
                        ];

                        if (!$this->contientProfAnglais($idsProfs, $profsById)) {
                            $warnings[] = "Le PFE '{$pfe->sujet}' est en anglais, mais aucun professeur d'anglais n'a pu être affecté sans déséquilibrer la répartition.";
                        }
                    }
                }

                if (!$combinaison) {
                    $success = false;
                    $pfeNonPlace = $pfe;
                    break;
                }

                $this->ajouterSoutenanceTemporaire(
                    $planning,
                    $etat,
                    $pfe,
                    $combinaison,
                    $profsById
                );
            }

            if ($success) {
                DB::transaction(function () use ($planning) {
                    // On régénère le planning proprement.
                    Soutenance::query()->delete();

                    foreach ($planning as $ligne) {
                        Soutenance::create([
                            'date_soutenance' => $ligne['date'],
                            'heure_debut' => $ligne['heure'],
                            'salle' => $ligne['salle'],
                            'id_pfe' => $ligne['id_pfe'],
                            'id_jury1' => $ligne['id_jury1'],
                            'id_jury2' => $ligne['id_jury2'],
                        ]);
                    }
                });

                return [
                    'created' => count($planning),
                    'errors' => [],
                    'warnings' => $warnings,
                    'planning' => $planning,
                ];
            }
        }

        return [
            'created' => 0,
            'errors' => [
                "Impossible de placer tous les PFEs. Dernier PFE non placé : " .
                ($pfeNonPlace?->sujet ?? 'inconnu')
            ],
            'warnings' => [],
            'planning' => [],
        ];
    }

    private function chercherMeilleureCombinaison(
        Pfe $pfe,
        $professeurs,
        $profsById,
        array $dates,
        array $creneaux,
        array $salles,
        array $etat,
        bool $exigerProfAnglais
    ): ?array {
        $meilleureCombinaison = null;
        $meilleurScore = PHP_INT_MAX;

        $idEncadrant = $pfe->id_encadrant;

        foreach ($dates as $date) {
            foreach ($creneaux as $heure) {
                foreach ($salles as $salle) {
                    if ($this->salleOccupee($date, $heure, $salle, $etat)) {
                        continue;
                    }

                    if (!$this->professeurDisponible($idEncadrant, $date, $heure, $etat)) {
                        continue;
                    }

                    foreach ($professeurs as $jury1) {
                        $idJury1 = $jury1->id_professeur;

                        if ($idJury1 == $idEncadrant) {
                            continue;
                        }

                        if (!$this->professeurDisponible($idJury1, $date, $heure, $etat)) {
                            continue;
                        }

                        foreach ($professeurs as $jury2) {
                            $idJury2 = $jury2->id_professeur;

                            if ($idJury2 == $idEncadrant || $idJury2 == $idJury1) {
                                continue;
                            }

                            if (!$this->professeurDisponible($idJury2, $date, $heure, $etat)) {
                                continue;
                            }

                            $idsProfs = [$idEncadrant, $idJury1, $idJury2];

                            if ($exigerProfAnglais) {
                                if (!$this->respecteContrainteAnglaisEquitable($pfe, $idsProfs, $profsById, $etat)) {
                                    continue;
                                }
                            }

                            $score = $this->calculerScore($pfe, $date, $heure, $idsProfs, $etat);

                            if ($score < $meilleurScore) {
                                $meilleurScore = $score;

                                $meilleureCombinaison = [
                                    'date' => $date,
                                    'heure' => $heure,
                                    'salle' => $salle,
                                    'id_jury1' => $idJury1,
                                    'id_jury2' => $idJury2,
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $meilleureCombinaison;
    }

    private function ajouterSoutenanceTemporaire(
        array &$planning,
        array &$etat,
        Pfe $pfe,
        array $combinaison,
        $profsById
    ): void {
        $date = $combinaison['date'];
        $heure = $combinaison['heure'];
        $salle = $combinaison['salle'];

        $idEncadrant = $pfe->id_encadrant;
        $idJury1 = $combinaison['id_jury1'];
        $idJury2 = $combinaison['id_jury2'];

        $idsProfs = [$idEncadrant, $idJury1, $idJury2];
        $filiere = $this->getFiliere($pfe);

        $planning[] = [
            'date' => $date,
            'heure' => $heure,
            'salle' => $salle,
            'id_pfe' => $pfe->id_pfe,
            'id_jury1' => $idJury1,
            'id_jury2' => $idJury2,
            'pfe' => $pfe->sujet,
            'filiere' => $filiere,
            'encadrant' => $this->nomProfesseur($profsById->get($idEncadrant)),
            'jury1' => $this->nomProfesseur($profsById->get($idJury1)),
            'jury2' => $this->nomProfesseur($profsById->get($idJury2)),
        ];

        // Marquer la salle comme occupée.
        $etat['salleOccupee'][$date][$heure][$salle] = true;

        // Marquer les professeurs comme occupés.
        foreach ($idsProfs as $idProf) {
            $etat['profOccupe'][$date][$heure][$idProf] = true;

            $etat['chargeProfJour'][$idProf][$date] =
                ($etat['chargeProfJour'][$idProf][$date] ?? 0) + 1;

            $etat['chargeProfTotal'][$idProf] =
                ($etat['chargeProfTotal'][$idProf] ?? 0) + 1;

            $etat['profHeure'][$idProf][$heure] =
                ($etat['profHeure'][$idProf][$heure] ?? 0) + 1;
        }

        // Marquer la filière dans ce jour.
        $etat['filiereJour'][$filiere][$date] =
            ($etat['filiereJour'][$filiere][$date] ?? 0) + 1;
    }

    private function calculerScore(Pfe $pfe, string $date, string $heure, array $idsProfs, array $etat): int
    {
        $filiere = $this->getFiliere($pfe);

        $score = 0;

        // Équilibrer les filières sur les jours.
        $score += ($etat['filiereJour'][$filiere][$date] ?? 0) * 100;

        foreach ($idsProfs as $idProf) {
            // Équilibrer les soutenances d'un prof sur les jours.
            $score += ($etat['chargeProfJour'][$idProf][$date] ?? 0) * 50;

            // Équilibrer les participations entre les professeurs.
            $score += ($etat['chargeProfTotal'][$idProf] ?? 0) * 20;

            // Éviter qu'un prof soit toujours dans le même créneau.
            $score += ($etat['profHeure'][$idProf][$heure] ?? 0) * 10;
        }

        return $score;
    }

    private function professeurDisponible(int $idProf, string $date, string $heure, array $etat): bool
    {
        // Même date + même heure : interdit.
        if (isset($etat['profOccupe'][$date][$heure][$idProf])) {
            return false;
        }

        if (!isset($etat['profOccupe'][$date])) {
            return true;
        }

        $minutes = $this->minutesDepuisMinuit($heure);

        foreach ($etat['profOccupe'][$date] as $heureOccupee => $profs) {
            if (!isset($profs[$idProf])) {
                continue;
            }

            $minutesOccupees = $this->minutesDepuisMinuit($heureOccupee);
            $difference = abs($minutes - $minutesOccupees);

            // Deux soutenances consécutives : interdit.
            if ($difference === 60) {
                return false;
            }
        }

        return true;
    }

    private function salleOccupee(string $date, string $heure, string $salle, array $etat): bool
    {
        return isset($etat['salleOccupee'][$date][$heure][$salle]);
    }

    private function respecteContrainteAnglaisEquitable(Pfe $pfe, array $idsProfs, $profsById, array $etat): bool
    {
        if (!$this->isPfeAnglais($pfe)) {
            return true;
        }

        $encadrant = $profsById->get($pfe->id_encadrant);

        // Si l'encadrant est déjà prof d'anglais, la contrainte est respectée.
        if ($encadrant && $this->isProfAnglais($encadrant)) {
            return true;
        }

        // Sinon, on essaie d'avoir un jury anglais qui n'a pas encore dépassé sa charge équitable.
        foreach ($idsProfs as $idProf) {
            if ($idProf == $pfe->id_encadrant) {
                continue;
            }

            $prof = $profsById->get($idProf);

            if (!$prof) {
                continue;
            }

            if ($this->isProfAnglais($prof)) {
                return ($etat['chargeProfTotal'][$idProf] ?? 0) < $etat['chargeMaxProf'];
            }
        }

        return false;
    }

    private function contientProfAnglais(array $idsProfs, $profsById): bool
    {
        foreach ($idsProfs as $idProf) {
            $prof = $profsById->get($idProf);

            if ($prof && $this->isProfAnglais($prof)) {
                return true;
            }
        }

        return false;
    }

    private function initialiserEtat($professeurs, int $totalPfes): array
    {
        $chargeProfTotal = [];
        $chargeProfJour = [];
        $profHeure = [];

        foreach ($professeurs as $professeur) {
            $id = $professeur->id_professeur;

            $chargeProfTotal[$id] = 0;
            $chargeProfJour[$id] = [];
            $profHeure[$id] = [];
        }

        return [
            'salleOccupee' => [],
            'profOccupe' => [],
            'chargeProfJour' => $chargeProfJour,
            'chargeProfTotal' => $chargeProfTotal,
            'filiereJour' => [],
            'profHeure' => [],

            // Chaque soutenance contient 3 professeurs : encadrant + jury1 + jury2.
            'chargeMaxProf' => (int) ceil(($totalPfes * 3) / max($professeurs->count(), 1)),
        ];
    }

    private function genererDates(string $dateDebut, int $nombreJours): array
    {
        $dates = [];
        $date = Carbon::parse($dateDebut);

        for ($i = 0; $i < $nombreJours; $i++) {
            $dates[] = $date->copy()->addDays($i)->toDateString();
        }

        return $dates;
    }

    private function isPfeAnglais(Pfe $pfe): bool
    {
        $langue = strtolower(trim((string) $pfe->langue));

        return in_array($langue, ['anglais', 'english', 'eng', 'en'], true);
    }

    private function isProfAnglais(Professeur $professeur): bool
    {
        $specialite = strtolower(trim((string) $professeur->specialite));

        return str_contains($specialite, 'anglais')
            || str_contains($specialite, 'english');
    }

    private function getFiliere(Pfe $pfe): string
    {
        return strtoupper(trim((string) ($pfe->etudiant?->filiere ?? 'NON_DEFINIE')));
    }

    private function minutesDepuisMinuit(string $heure): int
    {
        $parts = explode(':', $heure);

        return ((int) $parts[0]) * 60 + ((int) $parts[1]);
    }

    private function nomProfesseur(?Professeur $professeur): string
    {
        if (!$professeur) {
            return 'Professeur inconnu';
        }

        return trim($professeur->nom . ' ' . $professeur->prenom);
    }
}