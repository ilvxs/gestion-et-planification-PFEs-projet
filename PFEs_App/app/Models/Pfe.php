<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pfe extends Model
{
    protected $table = 'pfes';
    protected $primaryKey = 'id_pfe';

    protected $fillable = [
        'sujet',
        'langue',
        'id_etudiant',
        'id_encadrant'
    ];

    public function etudiant()
    {
        return $this->belongsTo(Etudiant::class, 'id_etudiant', 'id_etudiant');
    }

    public function encadrant()
    {
        return $this->belongsTo(Professeur::class, 'id_encadrant', 'id_professeur');
    }

    public function soutenance()
    {
        return $this->hasOne(Soutenance::class, 'id_pfe', 'id_pfe');
    }
}