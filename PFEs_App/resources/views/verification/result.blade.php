<x-layout title="Vérification complète">

    <h1>Vérification complète</h1>

    <p>
        Cette page vérifie les deux parties :
        <strong>l’affectation des encadrants</strong> et
        <strong>le planning des soutenances</strong>.
    </p>

    @if(session('success'))
        <p style="color: green; font-weight: bold;">
            {{ session('success') }}
        </p>
    @endif

    @if(session('error'))
        <p style="color: red; font-weight: bold;">
            {{ session('error') }}
        </p>
    @endif

    <hr>

    @if($isValid)
        <p style="color: green; font-weight: bold; font-size: 18px;">
            ✅ Vérification réussie. Vous pouvez passer à la génération des documents.
        </p>
    @else
        <p style="color: red; font-weight: bold; font-size: 18px;">
            ❌ Vérification échouée. Corrigez les erreurs avant de générer les documents.
        </p>
    @endif

    <hr>

    {{-- ===================================================== --}}
    {{-- 1. Vérification de l'affectation --}}
    {{-- ===================================================== --}}

    <h2>1. Vérification de l’affectation</h2>

    @if(!empty($affectation['checks']))
        <ul>
            @foreach($affectation['checks'] as $check)
                <li>
                    @if($check['status'] === 'ok')
                        <span style="color: green;">
                            ✅ {{ $check['message'] }}
                        </span>
                    @elseif($check['status'] === 'warning')
                        <span style="color: orange;">
                            ⚠️ {{ $check['message'] }}
                        </span>
                    @else
                        <span style="color: red;">
                            ❌ {{ $check['message'] }}
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if(!empty($affectation['errors']))
        <h3 style="color: red;">Erreurs d’affectation</h3>

        <ul>
            @foreach($affectation['errors'] as $error)
                <li style="color: red;">
                    {{ $error }}
                </li>
            @endforeach
        </ul>
    @endif

    @if(!empty($affectation['warnings']))
        <h3 style="color: orange;">Alertes d’affectation</h3>

        <ul>
            @foreach($affectation['warnings'] as $warning)
                <li style="color: orange;">
                    {{ $warning }}
                </li>
            @endforeach
        </ul>
    @endif

    @if(!empty($affectation['stats']))
        <h3>Statistiques de l’affectation</h3>

        <table border="1" cellpadding="8">
            <tbody>
                <tr>
                    <th>Total PFEs</th>
                    <td>{{ $affectation['stats']['total_pfes'] ?? 0 }}</td>
                </tr>

                <tr>
                    <th>PFEs affectés</th>
                    <td>{{ $affectation['stats']['pfes_affectes'] ?? 0 }}</td>
                </tr>

                <tr>
                    <th>PFEs sans encadrant</th>
                    <td>{{ $affectation['stats']['pfes_sans_encadrant'] ?? 0 }}</td>
                </tr>

                <tr>
                    <th>PFEs anglais</th>
                    <td>{{ $affectation['stats']['pfes_anglais'] ?? 0 }}</td>
                </tr>

                <tr>
                    <th>Professeurs</th>
                    <td>{{ $affectation['stats']['professeurs'] ?? 0 }}</td>
                </tr>

                <tr>
                    <th>Professeurs anglais</th>
                    <td>{{ $affectation['stats']['professeurs_anglais'] ?? 0 }}</td>
                </tr>

                <tr>
                    <th>Charge minimale</th>
                    <td>{{ $affectation['stats']['min_charge'] ?? 0 }}</td>
                </tr>

                <tr>
                    <th>Charge maximale</th>
                    <td>{{ $affectation['stats']['max_charge'] ?? 0 }}</td>
                </tr>

                <tr>
                    <th>Moyenne</th>
                    <td>{{ $affectation['stats']['moyenne_charge'] ?? 0 }}</td>
                </tr>

                <tr>
                    <th>Tolérance</th>
                    <td>{{ $affectation['stats']['tolerance'] ?? 0 }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    @if(!empty($affectation['pfes_anglais_sans_encadrant_anglais']))
        <h3 style="color: red;">PFEs anglais sans encadrant anglais</h3>

        <table border="1" cellpadding="8">
            <thead>
                <tr>
                    <th>ID PFE</th>
                    <th>Sujet</th>
                    <th>Langue</th>
                    <th>Filière</th>
                    <th>Encadrant</th>
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
    @endif

    @if(!empty($affectation['repartition_encadrants']))
        <h3>Répartition des encadrants</h3>

        <table border="1" cellpadding="8">
            <thead>
                <tr>
                    <th>Professeur</th>
                    <th>Spécialité</th>
                    <th>Nombre de PFEs encadrés</th>
                </tr>
            </thead>

            <tbody>
                @foreach($affectation['repartition_encadrants'] as $prof)
                    <tr>
                        <td>{{ $prof['professeur'] }}</td>
                        <td>{{ $prof['specialite'] }}</td>
                        <td>{{ $prof['nombre_pfes'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <hr>

    {{-- ===================================================== --}}
    {{-- 2. Vérification du planning --}}
    {{-- ===================================================== --}}

    <h2>2. Vérification du planning</h2>

    @if(!empty($planning['checks']))
        <ul>
            @foreach($planning['checks'] as $check)
                <li>
                    @if($check['status'] === 'ok')
                        <span style="color: green;">
                            ✅ {{ $check['message'] }}
                        </span>
                    @elseif($check['status'] === 'warning')
                        <span style="color: orange;">
                            ⚠️ {{ $check['message'] }}
                        </span>
                    @else
                        <span style="color: red;">
                            ❌ {{ $check['message'] }}
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if(!empty($planning['errors']))
        <h3 style="color: red;">Erreurs du planning</h3>

        <ul>
            @foreach($planning['errors'] as $error)
                <li style="color: red;">
                    {{ $error }}
                </li>
            @endforeach
        </ul>
    @endif

    @if(!empty($planning['warnings']))
        <h3 style="color: orange;">Alertes du planning</h3>

        <ul>
            @foreach($planning['warnings'] as $warning)
                <li style="color: orange;">
                    {{ $warning }}
                </li>
            @endforeach
        </ul>
    @endif

    @if(!empty($planning['stats']))
        <h3>Statistiques du planning</h3>

        <table border="1" cellpadding="8">
            <tbody>
                <tr>
                    <th>Total PFEs</th>
                    <td>{{ $planning['stats']['total_pfes'] ?? 0 }}</td>
                </tr>

                <tr>
                    <th>Total soutenances</th>
                    <td>{{ $planning['stats']['total_soutenances'] ?? 0 }}</td>
                </tr>

                <tr>
                    <th>Conflits jurys</th>
                    <td>{{ $planning['stats']['conflits_jurys'] ?? 0 }}</td>
                </tr>

                <tr>
                    <th>Conflits salles</th>
                    <td>{{ $planning['stats']['conflits_salles'] ?? 0 }}</td>
                </tr>

                <tr>
                    <th>Conflits profs même horaire</th>
                    <td>{{ $planning['stats']['conflits_profs_meme_horaire'] ?? 0 }}</td>
                </tr>

                <tr>
                    <th>Conflits consécutifs</th>
                    <td>{{ $planning['stats']['conflits_consecutifs'] ?? 0 }}</td>
                </tr>

                <tr>
                    <th>Soutenances dimanche</th>
                    <td>{{ $planning['stats']['soutenances_dimanche'] ?? 0 }}</td>
                </tr>

                <tr>
                    <th>Soutenances sans 2 profs info</th>
                    <td>{{ $planning['stats']['soutenances_sans_deux_info'] ?? 0 }}</td>
                </tr>

                <tr>
                    <th>PFEs anglais sans prof anglais</th>
                    <td>{{ $planning['stats']['pfes_anglais_sans_prof_anglais'] ?? 0 }}</td>
                </tr>

                <tr>
                    <th>Charge max prof</th>
                    <td>{{ $planning['stats']['charge_max_prof'] ?? 0 }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    @if(!empty($planning['repartition_profs']))
        <h3>Répartition des soutenances par professeur</h3>

        <table border="1" cellpadding="8">
            <thead>
                <tr>
                    <th>Professeur</th>
                    <th>Spécialité</th>
                    <th>Comme encadrant</th>
                    <th>Comme jury 1</th>
                    <th>Comme jury 2</th>
                    <th>Total</th>
                </tr>
            </thead>

            <tbody>
                @foreach($planning['repartition_profs'] as $prof)
                    <tr>
                        <td>{{ $prof['professeur'] }}</td>
                        <td>{{ $prof['specialite'] }}</td>
                        <td>{{ $prof['encadrant'] }}</td>
                        <td>{{ $prof['jury1'] }}</td>
                        <td>{{ $prof['jury2'] }}</td>
                        <td><strong>{{ $prof['total'] }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(!empty($planning['repartition_filieres']))
        <h3>Répartition des filières par jour</h3>

        <table border="1" cellpadding="8">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Filière</th>
                    <th>Nombre de soutenances</th>
                </tr>
            </thead>

            <tbody>
                @foreach($planning['repartition_filieres'] as $date => $filieres)
                    @foreach($filieres as $filiere => $nombre)
                        <tr>
                            <td>{{ $date }}</td>
                            <td>{{ $filiere }}</td>
                            <td>{{ $nombre }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    @endif

    <hr>

    {{-- ===================================================== --}}
    {{-- Boutons --}}
    {{-- ===================================================== --}}

    @if($isValid)
        <a href="{{ route('verification.continuer') }}"
           style="display:inline-block; padding:10px 16px; background:#16a34a; color:white; text-decoration:none; border-radius:6px;">
            Continuer vers la génération des documents
        </a>
    @else
        <p style="color: red; font-weight: bold;">
            Les documents sont bloqués jusqu’à correction des erreurs.
        </p>
    @endif

    <br><br>

    <a href="{{ route('planning.index') }}">
        Retour au planning
    </a>

</x-layout>