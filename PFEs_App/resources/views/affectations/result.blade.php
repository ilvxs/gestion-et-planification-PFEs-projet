<x-layout title="Résultat affectation">

    <h1>Résultat de l'affectation</h1>

    <p>
        PFEs affectés : 
        <strong>{{ $result['affected'] ?? 0 }}</strong>
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
            Affectation terminée avec succès.
        </p>
    @endif

    @if(!empty($result['repartition']))
        <h2>Répartition par professeur</h2>

        <table border="1" cellpadding="8">
            <thead>
                <tr>
                    <th>Professeur</th>
                    <th>Specilite</th>
                    <th>Nombre de PFEs encadrés</th>
                </tr>
            </thead>

            <tbody>
                @foreach($result['repartition'] as $professeur)
                    <tr>
                        <td>{{ $professeur['nom'] }}</td>
                        <td>{{ $professeur['specialite'] }}</td>
                        <td>{{ $professeur['total'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(!empty($result['warnings']))
        <h2 style="color: orange;">Alertes</h2>

        <ul>
            @foreach($result['warnings'] as $warning)
                <li>{{ $warning }}</li>
            @endforeach
        </ul>
    @endif

    <br>

    <a href="{{ route('affectations.index') }}">
        Retour
    </a>

</x-layout>