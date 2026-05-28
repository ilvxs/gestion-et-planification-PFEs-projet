<x-layout title="Importation des données">
    <span class="step-badge">Étape 1 / 5</span>
    <h1 class="mb-3">Importation des données PFE</h1>
    <p class="text-muted mb-4">
        Importez le fichier des étudiants/PFEs, le fichier des professeurs, puis choisissez la date de début et les salles disponibles.
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

        <div class="col-md-6">
            <label class="form-label fw-semibold">Fichier des étudiants et PFEs</label>
            <input type="file" name="students_file" class="form-control">
            <small class="text-muted">Colonnes attendues : CNE, nom, prénom, email, filière, sujet, langue.</small>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">Fichier des professeurs</label>
            <input type="file" name="professeurs_file" class="form-control">
            <small class="text-muted">Colonnes attendues : nom, prénom, spécialité.</small>
        </div>

        <div class="col-md-4">
            <label class="form-label fw-semibold">Date de début des soutenances</label>
            <input type="date" name="date_soutenance" class="form-control" value="{{ old('date_soutenance') }}">
        </div>

        <div class="col-12">
            <label class="form-label fw-semibold">Créneaux disponibles</label>

            <div class="row g-2">
                @foreach(['08:00','09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'] as $creneau)
                    <div class="col-md-2 col-6">
                        <label class="border rounded p-2 w-100 bg-light">
                            <input
                                type="checkbox"
                                name="creneaux[]"
                                value="{{ $creneau }}"
                                {{ in_array($creneau, old('creneaux', [])) ? 'checked' : '' }}
                            >
                            {{ $creneau }}
                        </label>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="col-12">
            <label class="form-label fw-semibold">Salles disponibles</label>
            <div class="row g-2">
                @foreach(['Salle A', 'Salle B', 'Salle C', 'Salle D', 'Salle E'] as $salle)
                    <div class="col-md-2 col-6">
                        <label class="border rounded p-2 w-100 bg-light">
                            <input type="checkbox" name="salles[]" value="{{ $salle }}" {{ in_array($salle, old('salles', [])) ? 'checked' : '' }}>
                            {{ $salle }}
                        </label>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-main px-4">
                Importer toutes les données
            </button>
        </div>
    </form>
</x-layout>
