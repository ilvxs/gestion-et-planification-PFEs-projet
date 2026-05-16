<x-layout title="Résultat planning">

    <h1>Résultat de génération du planning</h1>

    <p>
        Soutenances créées :
        <strong>{{ $result['created'] ?? 0 }}</strong>
    </p>

    @if(!empty($result['errors']))
        <h2 style="color: red;">Erreurs</h2>

        <ul>
            @foreach($result['errors'] as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @else
        <p style="color: green;">
            Planning généré avec succès.
        </p>
    @endif

    @if(!empty($result['warnings']))
        <h2 style="color: orange;">Alertes</h2>

        <ul>
            @foreach($result['warnings'] as $warning)
                <li>{{ $warning }}</li>
            @endforeach
        </ul>
    @endif

    @if(!empty($result['prof_stats']))

        <h2>Nombre de soutenances par professeur</h2>

        <table border="1" cellpadding="8">

            <thead>
                <tr>
                    <th>Professeur</th>
                    <th>Nombre de participations</th>
                </tr>
            </thead>

            <tbody>

                @foreach($result['prof_stats'] as $stat)

                    <tr>
                        <td>{{ $stat['nom'] }}</td>

                        <td>{{ $stat['count'] }}</td>
                    </tr>

                @endforeach

            </tbody>

        </table>

    @endif

    @if(!empty($result['planning']))
        <h2>Planning généré</h2>

        <table border="1" cellpadding="8">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Heure</th>
                    <th>Salle</th>
                    <th>PFE</th>
                    <th>Filière</th>
                    <th>Encadrant</th>
                    <th>Spécialité Encadrant</th>
                    <th>Jury 1</th>
                    <th>Spécialité Jury 1</th>
                    <th>Jury 2</th>
                    <th>Spécialité Jury 2</th>
                </tr>
            </thead>

            <tbody>
                @foreach($result['planning'] as $soutenance)
                    <tr>
                        <td>{{ $soutenance['date'] }}</td>
                        <td>{{ $soutenance['heure'] }}</td>
                        <td>{{ $soutenance['salle'] }}</td>
                        <td>{{ $soutenance['pfe'] }}</td>
                        <td>{{ $soutenance['filiere'] }}</td>
                        <td>{{ $soutenance['encadrant'] }}</td>
                        <td>{{ $soutenance['specialite_encadrant'] }}</td>
                        <td>{{ $soutenance['jury1'] }}</td>
                        <td>{{ $soutenance['specialite_jury1'] }}</td>
                        <td>{{ $soutenance['jury2'] }}</td>
                        <td>{{ $soutenance['specialite_jury2'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <br>

    <a href="{{ route('planning.index') }}">
        Retour
    </a>

</x-layout>