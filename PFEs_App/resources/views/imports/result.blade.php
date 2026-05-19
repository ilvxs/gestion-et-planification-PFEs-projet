<x-layout title="Résultat importation">
    <span class="step-badge">Étape 1 terminée</span>
    <h1 class="mb-4">{{ $title ?? 'Résultat Importation' }}</h1>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="border rounded p-3 bg-light">
                <div class="text-muted">Étudiants importés</div>
                <div class="fs-3 fw-bold">{{ $result['students_imported'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border rounded p-3 bg-light">
                <div class="text-muted">PFEs créés</div>
                <div class="fs-3 fw-bold">{{ $result['pfes_imported'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border rounded p-3 bg-light">
                <div class="text-muted">Professeurs importés</div>
                <div class="fs-3 fw-bold">{{ $result['professeurs_imported'] ?? 0 }}</div>
            </div>
        </div>
    </div>

    @if(!empty($result['errors']))
        <div class="alert alert-danger">
            <h5>Erreurs détectées</h5>
            <ul class="mb-0">
                @foreach($result['errors'] as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>

        <a href="{{ route('imports.index') }}" class="btn btn-outline-secondary">
            Retour à l'importation
        </a>
    @else
        <div class="alert alert-success">
            Importation terminée sans erreurs.
        </div>

        <hr class="my-4">

        <section>
            <span class="step-badge">Étape 2 / 5</span>
            <h2>Affectation des encadrants</h2>
            <p class="text-muted">
                Les données sont prêtes. Lancez maintenant l’affectation automatique des encadrants.
            </p>

            <form action="{{ route('affectations.generate') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-main">
                    Lancerr l’affectation des encadrants
                </button>
            </form>
        </section>
    @endif
</x-layout>
