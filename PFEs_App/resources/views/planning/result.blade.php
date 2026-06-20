<x-layout title="Résultat planning">
    <span class="step-badge">Étape 3 terminée</span>

    <h1 class="mb-4">Résultat de génération du planning</h1>

    <div class="border rounded p-3 bg-light mb-4">
        <div class="text-muted">Soutenances créées</div>
        <div class="fs-3 fw-bold">{{ $result['created'] ?? 0 }}</div>
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
    @else
        <div class="alert alert-success">
            Le planning a été généré et enregistré avec succès.
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

    <hr class="my-4">

    <section>
        <span class="step-badge">Étape 4 / 5</span>

        <h2>Vérification complète</h2>

        @if(empty($result['errors']) && (($result['created'] ?? 0) > 0))
            <p class="text-muted">
                Le planning est généré. Lancez maintenant la vérification de l’affectation et du planning.
            </p>

            <a href="{{ route('planning.viewer') }}" class="btn btn-outline-primary me-2">
                Voir le planning interactif
            </a>

            <a href="{{ route('verification.index') }}" class="btn btn-main">
                Vérifier l’affectation et le planning
            </a>
        @else
            <p class="text-danger fw-semibold">
                La vérification est bloquée parce que le planning n’a pas été généré correctement.
            </p>
        @endif
    </section>
</x-layout>