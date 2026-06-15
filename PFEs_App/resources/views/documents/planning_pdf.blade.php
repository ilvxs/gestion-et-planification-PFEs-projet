<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Planning des soutenances PFE</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            margin: 8px;
        }

        .header {
            text-align: center;
            margin-bottom: 12px;
            line-height: 1.35;
        }

        .header .school {
            font-size: 15px;
            font-weight: bold;
        }

        .header .dept {
            font-size: 13px;
            font-weight: bold;
        }

        .header .title {
            font-size: 12px;
            margin-top: 3px;
        }

        .header .session {
            font-style: italic;
            font-size: 11px;
        }

        .header .year {
            font-size: 11px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th {
            background: #000;
            color: #fff;
            border: 1px solid #111;
            padding: 4px 2px;
            font-size: 7.5px;
            text-align: center;
        }

        td {
            border: 1px solid #111;
            padding: 3px 2px;
            font-size: 7.2px;
            vertical-align: middle;
        }

        .id-col { width: 20px; text-align: center; }
        .prof-col { width: 110px; font-weight: bold; }
        .date-col { width: 55px; text-align: center; background: #92d050; font-weight: bold; }
        .heure-col { width: 35px; text-align: center; background: #ffff00; font-weight: bold; }
        .salle-col { width: 40px; text-align: center; font-weight: bold; }
        .nom-col { width: 80px; background: #d9eaf7; }
        .prenom-col { width: 70px; background: #d9eaf7; }
        .filiere-col { width: 35px; text-align: center; background: #e2f0d9; font-weight: bold; }

        .prof-cell {
            color: #000;
            font-weight: bold;
        }
    </style>
</head>

<body>

<div class="header">
    <div class="school">Ecole Nationale des Sciences Appliquées - Al Hoceima</div>
    <div class="dept">Département Mathématiques et Informatique</div>
    <div class="title">Planning des soutenances des Projets de Fin d'Etude</div>
    <div class="session">(Première Session)</div>
    <div class="year">Année Universitaire {{ config('pfe.annee_universitaire') }}</div>
</div>

<table>
    <thead>
        <tr>
            <th class="id-col">ID</th>
            <th class="prof-col">Encadrant</th>
            <th class="prof-col">Membre de jury 1</th>
            <th class="prof-col">Membre de jury 2</th>
            <th class="date-col">Date</th>
            <th class="heure-col">Heure</th>
            <th class="salle-col">Salle</th>
            <th class="nom-col">Nom d'étudiant</th>
            <th class="prenom-col">Prénom d'étudiant</th>
            <th class="filiere-col">Filière</th>
        </tr>
    </thead>

    <tbody>
        @foreach($soutenances as $index => $soutenance)
            @php
                $encadrant = $soutenance->pfe?->encadrant;
                $jury1 = $soutenance->jury1;
                $jury2 = $soutenance->jury2;

                $encColor = $profColors[$encadrant?->id_professeur] ?? '#ffffff';
                $j1Color = $profColors[$jury1?->id_professeur] ?? '#ffffff';
                $j2Color = $profColors[$jury2?->id_professeur] ?? '#ffffff';

                $date = \Carbon\Carbon::parse($soutenance->date_soutenance)->format('d/m/Y');
                $heure = \Carbon\Carbon::parse($soutenance->heure_debut)->format('G\h');

                $filiere = strtoupper($soutenance->pfe?->etudiant?->filiere ?? '-');
            @endphp

            <tr>
                <td class="id-col">{{ $index + 1 }}</td>

                <td class="prof-cell prof-col" style="background: {{ $encColor }};">
                    {{ $encadrant?->nom }} {{ $encadrant?->prenom }}
                </td>

                <td class="prof-cell prof-col" style="background: {{ $j1Color }};">
                    {{ $jury1?->nom }} {{ $jury1?->prenom }}
                </td>

                <td class="prof-cell prof-col" style="background: {{ $j2Color }};">
                    {{ $jury2?->nom }} {{ $jury2?->prenom }}
                </td>

                <td class="date-col">{{ $date }}</td>
                <td class="heure-col">{{ $heure }}</td>
                <td class="salle-col">{{ $soutenance->salle?->nom }}</td>
                <td class="nom-col">{{ $soutenance->pfe?->etudiant?->nom }}</td>
                <td class="prenom-col">{{ $soutenance->pfe?->etudiant?->prenom }}</td>
                <td class="filiere-col">{{ $filiere }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>
