<?php

return [
    'duree_soutenance_minutes' => 60,

    'annee_universitaire' => (function () {
        $date = now();
        $annee = $date->month >= 9 ? $date->year : $date->year - 1;
        
        return $annee . '-' . ($annee + 1);
    })(),
];
