<x-layout title="Dashboard">
    <span class="step-badge">Dashboard</span>
    <h1 class="mb-3">Dashboard</h1>
    <p class="text-muted">
        Le Dashboard donne une vision globale de l’état de l’application et des statistiques principales.
    </p>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="border rounded p-3 bg-light">
                <div class="text-muted">Nombre total d’étudiants</div>
                <div class="fs-3 fw-bold">{{ $totalEtudiants }}</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="border rounded p-3 bg-light">
                <div class="text-muted">Nombre total de professeurs</div>
                <div class="fs-3 fw-bold">{{ $totalProfesseurs }}</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="border rounded p-3 bg-light">
                <div class="text-muted">Nombre total de salles</div>
                <div class="fs-3 fw-bold">{{ $totalSalles }}</div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="border rounded p-3 bg-light">
                <div class="text-muted">Nombre total de soutenances</div>
                <div class="fs-3 fw-bold">{{ $totalSoutenances }}</div>
            </div>
        </div>
    </div>

    <h2>Nombre des étudiants encadrés par professeur</h2>
    <div class="table-responsive mb-4">
        <table class="table table-bordered table-sm align-middle">
            <thead>
                <tr>
                    <th>Professeur</th>
                    <th>Spécialité</th>
                    <th>Étudiants encadrés</th>
                </tr>
            </thead>
            <tbody>
                @forelse($etudiantsParProfesseur as $item)
                    <tr>
                        <td>{{ $item['professeur'] }}</td>
                        <td>{{ $item['specialite'] }}</td>
                        <td>{{ $item['total'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-muted">Aucune donnée disponible.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h2>Nombre de soutenances par professeur</h2>
    <div class="table-responsive mb-4">
        <table class="table table-bordered table-sm align-middle">
            <thead>
                <tr>
                    <th>Professeur</th>
                    <th>Spécialité</th>
                    <th>Total soutenances</th>
                </tr>
            </thead>
            <tbody>
                @forelse($soutenancesParProfesseur as $item)
                    <tr>
                        <td>{{ $item['professeur'] }}</td>
                        <td>{{ $item['specialite'] }}</td>
                        <td>{{ $item['total'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-muted">Aucune soutenance générée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <h2>Nombre de soutenances par date</h2>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Nombre de soutenances</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($soutenancesParDate as $item)
                            <tr>
                                <td>{{ $item['date'] }}</td>
                                <td>{{ $item['total'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-muted">Aucune soutenance générée.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-lg-6">
            <h2>Nombre de soutenances par filière</h2>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Filière</th>
                            <th>Nombre de soutenances</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($soutenancesParFiliere as $item)
                            <tr>
                                <td>{{ $item['filiere'] }}</td>
                                <td>{{ $item['total'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-muted">Aucune soutenance générée.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <hr class="my-4">

    <h2>Nombre de soutenances par filière et par jour</h2>

    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Filière</th>
                    <th>Nombre de soutenances</th>
                </tr>
            </thead>

            <tbody>
                @forelse($soutenancesParFiliereParJour as $jour)
                    @foreach($jour['filieres'] as $filiere)
                        <tr>
                            <td>{{ $jour['date'] }}</td>
                            <td>{{ $filiere['filiere'] }}</td>
                            <td>{{ $filiere['total'] }}</td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="3" class="text-muted">
                            Aucune soutenance générée.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layout>
