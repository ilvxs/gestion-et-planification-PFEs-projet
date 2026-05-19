<x-layout title="Résultat affectation">
    <span class="step-badge">Étape 2 terminée</span>

    <h1 class="mb-4">Résultat de l'affectation</h1>

    <div class="border rounded p-3 bg-light mb-4">
        <div class="text-muted">PFEs affectés</div>
        <div class="fs-3 fw-bold">{{ $result['affected'] ?? 0 }}</div>
    </div>

    @if(!empty($result['errors']))
        <div class="alert alert-danger">
            <h5>Erreurs</h5>

            <ul class="mb-0">
                @foreach($result['errors'] as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>

        <a href="{{ route('imports.index') }}" class="btn btn-outline-secondary">
            Retour à l’importation
        </a>
    @else
        <div class="alert alert-success">
            Affectation terminée avec succès.
        </div>
    @endif

    @if(!empty($result['warnings']))
        <div class="alert alert-warning">
            <h5>Alertes</h5>

            <ul class="mb-0">
                @foreach($result['warnings'] as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(empty($result['errors']))
        <hr class="my-4">

        <section>
            <span class="step-badge">Étape 3 / 5</span>

            <h2>Génération du planning</h2>

            <p class="text-muted">
                L’affectation est terminée. Vous pouvez maintenant générer le planning des soutenances.
            </p>

            <form action="{{ route('planning.generate') }}" method="POST">
                @csrf

                <button type="submit" class="btn btn-main">
                    Générer le planning
                </button>
            </form>
        </section>
    @endif
</x-layout>