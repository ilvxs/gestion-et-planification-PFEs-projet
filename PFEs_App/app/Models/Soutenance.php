<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Soutenance extends Model
{
    protected $table = 'soutenances';
    protected $primaryKey = 'id_soutenance';

    protected $fillable = [
        'date_soutenance',
        'heure_debut',
        'id_salle',
        'id_pfe',
        'id_jury1',
        'id_jury2'
    ];

    public function pfe()
    {
        return $this->belongsTo(Pfe::class, 'id_pfe', 'id_pfe');
    }

    public function salle()
    {
        return $this->belongsTo(Salle::class, 'id_salle', 'id_salle');
    }

    public function jury1()
    {
        return $this->belongsTo(Professeur::class, 'id_jury1', 'id_professeur');
    }

    public function jury2()
    {
        return $this->belongsTo(Professeur::class, 'id_jury2', 'id_professeur');
    }
}
