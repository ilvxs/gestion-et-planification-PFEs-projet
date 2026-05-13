<x-layout title="Affectation des encadrants">

    <h1>Affectation automatique des encadrants</h1>

    <p>
        Cette étape permet d'affecter automatiquement les encadrants aux PFEs.
    </p>

    <form action="{{ route('affectations.generate') }}" method="POST">
        @csrf

        <button type="submit">
            Lancer l'affectation
        </button>
    </form>

</x-layout>