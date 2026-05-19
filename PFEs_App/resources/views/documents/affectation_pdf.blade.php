<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Affectation des encadrants PFE</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            margin: 12px;
        }

        .header-box {
            width: 78%;
            margin: 0 auto 12px auto;
            border: 2px solid #111;
            text-align: center;
            padding: 6px;
            line-height: 1.35;
        }

        .school {
            font-size: 13px;
            font-weight: bold;
        }

        .dept {
            font-size: 11px;
            font-weight: bold;
        }

        .title {
            font-size: 10px;
            font-weight: bold;
        }

        .year {
            font-size: 9px;
        }

        .legend {
            width: 220px;
            margin: 5px auto 14px auto;
            font-size: 9px;
        }

        .legend-row {
            display: table;
            width: 100%;
        }

        .legend-color {
            display: table-cell;
            width: 90px;
            height: 14px;
        }

        .legend-text {
            display: table-cell;
            padding-left: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #000;
            padding: 3px;
            font-size: 8px;
        }

        .main-title {
            background: #00b0f0;
            font-weight: bold;
            text-align: center;
            font-size: 10px;
        }

        .sub-title {
            background: #d9e2f3;
            font-style: italic;
            font-weight: bold;
            text-align: center;
        }

        .enc-col {
            width: 80px;
            font-weight: bold;
        }

        .student-cell {
            width: 88px;
        }

        .id-color {
            background: #f4b183;
        }

        .gi-color {
            background: #b4c6e7;
        }

        .tdia-color {
            background-color: #d6104f;
        }

        .unknown-color {
            background: #eeeeee;
        }
    </style>
</head>

<body>

<div class="header-box">
    <div class="school">Ecole Nationale des Sciences Appliquées - Al Hoceima</div>
    <div class="dept">Département Mathématiques et Informatique</div>
    <div class="title">Affectation des encadrants de Projet de Fin d'Etude</div>
    <div class="year">Année Universitaire {{ config('pfe.annee_universitaire') }}</div>
</div>

<div class="legend">
    <div class="legend-row">
        <div class="legend-color id-color"></div>
        <div class="legend-text">Filière ID</div>
    </div>
    <div class="legend-row">
        <div class="legend-color gi-color"></div>
        <div class="legend-text">Filière GI</div>
    </div>
    <div class="legend-row">
        <div class="legend-color tdia-color"></div>
        <div class="legend-text">Filière TDIA</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th colspan="2" class="main-title">Encadrant</th>
            <th colspan="8" class="main-title">Etudiants encadrés</th>
        </tr>

        <tr>
            <th class="sub-title">Nom</th>
            <th class="sub-title">Prénom</th>

            <th colspan="2" class="sub-title">Etudiant 1</th>
            <th colspan="2" class="sub-title">Etudiant 2</th>
            <th colspan="2" class="sub-title">Etudiant 3</th>
            <th colspan="2" class="sub-title">Etudiant 4</th>
        </tr>
    </thead>

    <tbody>
        @foreach($groupes as $idEncadrant => $pfes)
            @php
                $encadrant = $pfes->first()?->encadrant;
                $chunks = $pfes->values()->chunk(4);
            @endphp

            @foreach($chunks as $chunkIndex => $chunk)
                <tr>
                    <td class="enc-col">
                        {{ $chunkIndex === 0 ? strtoupper($encadrant?->nom) : '' }}
                    </td>

                    <td class="enc-col">
                        {{ $chunkIndex === 0 ? $encadrant?->prenom : '' }}
                    </td>

                    @for($i = 0; $i < 4; $i++)
                        @php
                            $pfe = $chunk[$i] ?? null;
                            $filiere = strtoupper(trim((string) ($pfe?->etudiant?->filiere ?? '')));
                            $colorClass = match($filiere) {
                                'ID',  'DATA' => 'id-color',
                                'GI', 'INFORMATIQUE' => 'gi-color',
                                'TDIA', 'TRANSFORMATION DIGITAL', 'IA', 'AI'  => 'tdia-color',
                                default => 'unknown-color',
                            };
                        @endphp

                        @if($pfe)
                            <td class="student-cell {{ $colorClass }}">
                                {{ strtoupper($pfe->etudiant?->nom) }}
                            </td>
                            <td class="student-cell {{ $colorClass }}">
                                {{ $pfe->etudiant?->prenom }}
                            </td>
                        @else
                            <td class="student-cell"></td>
                            <td class="student-cell"></td>
                        @endif
                    @endfor
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>

</body>
</html>