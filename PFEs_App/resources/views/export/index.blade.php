<x-layout title="Exportation des documents">
    <span class="step-badge">Exportation</span>
    <h1 class="mb-3">Exportation des documents</h1>
    <p class="text-muted">
        Exportez les documents : planning, affectations des encadrants et PVs.
    </p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if(!$planningGenerated)
        <div class="alert alert-danger">
            Aucun planning n’a été généré. Veuillez générer le planning avant d’exporter les documents.
        </div>

        <a href="{{ route('imports.index') }}" class="btn btn-main">
            Retour à l’importation
        </a>
    @elseif(!$verificationCompleted)
        <div class="alert alert-warning">
            Le planning existe avec <strong>{{ $totalSoutenances }}</strong> soutenance(s), mais la vérification complète n’a pas encore été validée.
        </div>

        <a href="{{ route('verification.index') }}" class="btn btn-main">
            Lancer la vérification
        </a>
    @else
        <div class="alert alert-success">
            Planning généré et vérifié. Vous pouvez exporter les documents.
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <h5>Planning des soutenances</h5>
                    <p class="text-muted">Document global du planning.</p>
                    <a href="{{ route('documents.planning') }}" class="btn btn-main">
                        Exporter PDF
                    </a>
                </div>
            </div>

            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <h5>Affectations des encadrants</h5>
                    <p class="text-muted">Liste des PFEs avec leurs encadrants.</p>
                    <a href="{{ route('documents.affectations') }}" class="btn btn-main">
                        Exporter PDF
                    </a>
                </div>
            </div>

            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <h5>PVs de soutenance</h5>
                    <p class="text-muted">PV pour les soutenances planifiées.</p>
                    <a href="{{ route('documents.pvs') }}" class="btn btn-main">
                        Exporter les PVs
                    </a>
                </div>
            </div>
        </div>
    @endif
</x-layout>
