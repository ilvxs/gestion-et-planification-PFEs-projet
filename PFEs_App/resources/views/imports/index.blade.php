<x-layout title="Importation des donnees">
    <span class="step-badge">Etape 1 / 5</span>
    <h1 class="mb-3">Importation des donnees PFE</h1>
    <p class="text-muted mb-4">
        Importez un seul fichier Excel contenant les trois feuilles : professeur, etudiant et salle.
    </p>

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>Veuillez corriger les erreurs suivantes :</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('imports.all') }}" method="POST" enctype="multipart/form-data" class="row g-4">
        @csrf

        <div class="col-md-8">
            <label class="form-label fw-semibold">Fichier Excel unifie</label>
            <input type="file" name="import_file" class="form-control">
            <small class="text-muted">
                Feuilles attendues : professeur, etudiant, salle.
                Professeur : nom, prenom, specialite.
                Etudiant : CNE, nom, prenom, email personnel, email academique, filiere, sujet, langue.
                Salle : nom, disponible optionnel.
            </small>
        </div>

        <div class="col-md-4">
            <label class="form-label fw-semibold">Date de debut des soutenances</label>
            <input type="date" name="date_soutenance" class="form-control" value="{{ old('date_soutenance') }}">
        </div>

        <div class="col-md-4">
            <label class="form-label fw-semibold">Date de fin des soutenances</label>
            <input type="date" name="date_fin_soutenance" class="form-control" value="{{ old('date_fin_soutenance') }}">
        </div>

        <div class="col-12">
            <label class="form-label fw-semibold">Horaires</label>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="border rounded p-3 bg-light h-100">
                        <div class="fw-semibold mb-3">Matin</div>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label for="heure_debut_matin" class="form-label">Heure de debut</label>
                                <input
                                    type="time"
                                    id="heure_debut_matin"
                                    name="heure_debut_matin"
                                    class="form-control"
                                    value="{{ old('heure_debut_matin', '08:00') }}"
                                >
                            </div>
                            <div class="col-sm-6">
                                <label for="heure_fin_matin" class="form-label">Heure de fin</label>
                                <input
                                    type="time"
                                    id="heure_fin_matin"
                                    name="heure_fin_matin"
                                    class="form-control"
                                    value="{{ old('heure_fin_matin', '12:00') }}"
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="border rounded p-3 bg-light h-100">
                        <div class="fw-semibold mb-3">Apres-midi</div>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label for="heure_debut_apres_midi" class="form-label">Heure de debut</label>
                                <input
                                    type="time"
                                    id="heure_debut_apres_midi"
                                    name="heure_debut_apres_midi"
                                    class="form-control"
                                    value="{{ old('heure_debut_apres_midi', '14:00') }}"
                                >
                            </div>
                            <div class="col-sm-6">
                                <label for="heure_fin_apres_midi" class="form-label">Heure de fin</label>
                                <input
                                    type="time"
                                    id="heure_fin_apres_midi"
                                    name="heure_fin_apres_midi"
                                    class="form-control"
                                    value="{{ old('heure_fin_apres_midi', '18:00') }}"
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-main px-4">
                Importer toutes les donnees
            </button>
        </div>
    </form>
</x-layout>
