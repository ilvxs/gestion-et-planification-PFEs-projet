<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Professeur extends Model
{
    protected $table = 'professeurs';
    protected $primaryKey = 'id_professeur';

    protected $fillable = [
        'nom',
        'prenom',
        'specialite'
    ];

    public function pfesEncadres()
    {
        return $this->hasMany(Pfe::class, 'id_encadrant', 'id_professeur');
    }

    public function soutenancesJury1()
    {
        return $this->hasMany(Soutenance::class, 'id_jury1', 'id_professeur');
    }

    public function soutenancesJury2()
    {
        return $this->hasMany(Soutenance::class, 'id_jury2', 'id_professeur');
    }
}