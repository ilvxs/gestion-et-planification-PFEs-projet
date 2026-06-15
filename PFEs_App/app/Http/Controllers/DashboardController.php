<?php

namespace App\Http\Controllers;

use App\Models\Etudiant;
use App\Models\Pfe;
use App\Models\Professeur;
use App\Models\Salle;
use App\Models\Soutenance;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $totalEtudiants = Etudiant::count();
        $totalProfesseurs = Professeur::count();
        $totalSalles = Salle::count();
        $totalSoutenances = Soutenance::count();

        $professeurs = Professeur::orderBy('nom')->orderBy('prenom')->get();

        $pfes = Pfe::with(['etudiant', 'encadrant'])->get();

        $soutenances = Soutenance::with([
            'pfe.etudiant',
            'pfe.encadrant',
            'jury1',
            'jury2',
        ])->get();

        $etudiantsParProfesseur = $professeurs->map(function ($professeur) use ($pfes) {
            $total = $pfes->filter(function ($pfe) use ($professeur) {
                return (int) $pfe->id_encadrant === (int) $professeur->id_professeur;
            })->count();

            return [
                'professeur' => trim($professeur->nom . ' ' . $professeur->prenom),
                'specialite' => $professeur->specialite,
                'total' => $total,
            ];
        })->sortByDesc('total')->values();

        $soutenancesParProfesseur = $professeurs->map(function ($professeur) use ($soutenances) {
            $idProf = (int) $professeur->id_professeur;

            $total = $soutenances->filter(function ($soutenance) use ($idProf) {
                return (int) ($soutenance->pfe?->id_encadrant) === $idProf
                    || (int) $soutenance->id_jury1 === $idProf
                    || (int) $soutenance->id_jury2 === $idProf;
            })->count();

            return [
                'professeur' => trim($professeur->nom . ' ' . $professeur->prenom),
                'specialite' => $professeur->specialite,
                'total' => $total,
            ];
        })->sortByDesc('total')->values();

        $soutenancesParDate = $soutenances
            ->groupBy(function ($soutenance) {
                return Carbon::parse($soutenance->date_soutenance)->toDateString();
            })
            ->map(function ($items, $date) {
                return [
                    'date' => $date,
                    'total' => $items->count(),
                ];
            })
            ->sortBy('date')
            ->values();

        $soutenancesParFiliere = $soutenances
            ->groupBy(function ($soutenance) {
                return strtoupper(trim((string) ($soutenance->pfe?->etudiant?->filiere ?? 'NON_DEFINIE')));
            })
            ->map(function ($items, $filiere) {
                return [
                    'filiere' => $filiere,
                    'total' => $items->count(),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $soutenancesParFiliereParJour = $soutenances
            ->groupBy(function ($soutenance) {
                return Carbon::parse($soutenance->date_soutenance)->toDateString();
            })
            ->map(function ($items, $date) {
                return [
                    'date' => $date,
                    'filieres' => $items
                        ->groupBy(function ($soutenance) {
                            return strtoupper(trim((string) ($soutenance->pfe?->etudiant?->filiere ?? 'NON_DEFINIE')));
                        })
                        ->map(function ($items, $filiere) {
                            return [
                                'filiere' => $filiere,
                                'total' => $items->count(),
                            ];
                        })
                        ->sortBy('filiere')
                        ->values(),
                ];
            })
            ->sortBy('date')
            ->values();

        return view('dashboard.index', compact(
            'totalEtudiants',
            'totalProfesseurs',
            'totalSalles',
            'totalSoutenances',
            'etudiantsParProfesseur',
            'soutenancesParProfesseur',
            'soutenancesParDate',
            'soutenancesParFiliere',
            'soutenancesParFiliereParJour'
        ));
    }
}
