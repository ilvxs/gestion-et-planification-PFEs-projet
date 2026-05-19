<x-layout title="Vérification complète">
    <span class="step-badge">Étape 4 terminée</span>
    <h1 class="mb-3">Vérification complète</h1>
    <p class="text-muted">
        Cette phase vérifie l'affectation des encadrants et le planning des soutenances.
    </p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if($isValid)
        <div class="alert alert-success fw-semibold">
            Vérification terminée avec succès. Les alertes ne bloquent pas l'exportation.
        </div>
    @else
        <div class="alert alert-danger fw-semibold">
            Vérification échouée. Corrigez les erreurs bloquantes avant l’exportation.
        </div>
    @endif

    <hr class="my-4">

    <h2>1. Vérification de l’affectation</h2>

    @foreach($affectation['checks'] ?? [] as $check)
        <div class="alert {{ $check['status'] === 'ok' ? 'alert-success' : ($check['status'] === 'warning' ? 'alert-warning' : 'alert-danger') }} py-2">
            @if($check['status'] === 'ok')
                ✅
            @elseif($check['status'] === 'warning')
                ⚠️
            @else
                ❌
            @endif
            {{ $check['message'] }}
        </div>
    @endforeach

    @if(!empty($affectation['errors']))
        <div class="alert alert-danger">
            <h5>Erreurs d’affectation</h5>
            <ul class="mb-0">
                @foreach($affectation['errors'] as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!empty($affectation['warnings']))
        <div class="alert alert-warning">
            <h5>Alertes d’affectation</h5>
            <ul class="mb-0">
                @foreach($affectation['warnings'] as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!empty($affectation['pfes_anglais_sans_encadrant_anglais']))
        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle">
                <thead>
                    <tr>
                        <th>ID PFE</th>
                        <th>Sujet</th>
                        <th>Langue</th>
                        <th>Filière</th>
                        <th>Encadrant actuel</th>
                        <th>Spécialité encadrant</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($affectation['pfes_anglais_sans_encadrant_anglais'] as $pfe)
                        <tr>
                            <td>{{ $pfe['id_pfe'] }}</td>
                            <td>{{ $pfe['sujet'] }}</td>
                            <td>{{ $pfe['langue'] }}</td>
                            <td>{{ $pfe['filiere'] }}</td>
                            <td>{{ $pfe['encadrant'] }}</td>
                            <td>{{ $pfe['specialite_encadrant'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <hr class="my-4">

    <h2>2. Vérification du planning</h2>

    @foreach($planning['checks'] ?? [] as $check)
        <div class="alert {{ $check['status'] === 'ok' ? 'alert-success' : ($check['status'] === 'warning' ? 'alert-warning' : 'alert-danger') }} py-2">
            @if($check['status'] === 'ok')
                ✅
            @elseif($check['status'] === 'warning')
                ⚠️
            @else
                ❌
            @endif
            {{ $check['message'] }}
        </div>
    @endforeach

    @if(!empty($planning['errors']))
        <div class="alert alert-danger">
            <h5>Erreurs du planning</h5>
            <ul class="mb-0">
                @foreach($planning['errors'] as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!empty($planning['warnings']))
        <div class="alert alert-warning">
            <h5>Alertes du planning</h5>
            <ul class="mb-0">
                @foreach($planning['warnings'] as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <hr class="my-4">

    <section>
        <span class="step-badge">Étape 5 / 5</span>
        <h2>Exportation des documents</h2>

        @if($isValid)
            <p class="text-muted">
                La vérification est valide. Vous pouvez exporter le planning, les affectations et les PVs.
            </p>

            <a href="{{ route('export.index') }}" class="btn btn-main">
                Continuer vers l’exportation
            </a>
        @else
            <p class="text-danger fw-semibold">
                L’exportation est bloquée jusqu’à correction des erreurs.
            </p>
        @endif
    </section>
</x-layout>
