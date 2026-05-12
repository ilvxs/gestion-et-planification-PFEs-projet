<x-layout title="Résultat Import">

    <h1>{{ $title }}</h1>

    <hr>

    {{-- Étudiants importés --}}
    @if(isset($result['students_imported']))

        <p>
            Étudiants importés :
            <strong>
                {{ $result['students_imported'] }}
            </strong>
        </p>

    @endif

    {{-- PFEs CRÉÉS --}}
    @if(isset($result['pfes_imported']))

        <p>
            PFEs créés :
            <strong>
                {{ $result['pfes_imported'] }}
            </strong>
        </p>

    @endif

    {{-- PROFESSEURS IMPORTÉS --}}
    @if(isset($result['professeurs_imported']))

        <p>
            Professeurs importés :
            <strong>
                {{ $result['professeurs_imported'] }}
            </strong>
        </p>

    @endif

    <hr>

    {{-- ERRORS --}}
    @if(count($result['errors']) > 0)

        <h2 class="error">
            Erreurs détectées
        </h2>

        <ul>

            @foreach($result['errors'] as $error)

                <li>
                    {{ $error }}
                </li>

            @endforeach

        </ul>

    @else

        <p class="success">
            Importation terminée sans erreurs.
        </p>

    @endif

    <hr>

    <a href="{{ route('imports.index') }}">

        Retour à la page d'import

    </a>

</x-layout>