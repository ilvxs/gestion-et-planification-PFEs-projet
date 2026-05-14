<x-layout title="Génération du planning">

    <h1>Génération du planning des soutenances</h1>

    <p>
        Cette étape permet de générer automatiquement le planning des soutenances
        après l’importation des données et l’affectation des encadrants.
    </p>

    <h3>Données utilisées</h3>

    <p>
        <strong>Date de début :</strong>
        {{ session('date_soutenance') ?? 'Non définie' }}
    </p>

    <p>
        <strong>Salles sélectionnées :</strong>
    </p>

    @if(session('salles'))
        <ul>
            @foreach(session('salles') as $salle)
                <li>{{ $salle }}</li>
            @endforeach
        </ul>
    @else
        <p style="color: red;">
            Aucune salle sélectionnée. Veuillez refaire l’importation.
        </p>
    @endif

    <p>
        <strong>Créneaux utilisés :</strong>
    </p>

    <ul>
        @foreach(config('pfe.creneaux') as $creneau)
            <li>{{ $creneau }}</li>
        @endforeach
    </ul>

    <form action="{{ route('planning.generate') }}" method="POST">
        @csrf

        <button type="submit">
            Générer le planning
        </button>
    </form>

</x-layout>