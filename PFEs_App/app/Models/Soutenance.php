<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Soutenance extends Model
{
    protected $primaryKey = 'id_soutenance';
    
    protected $fillable = ['date_soutenance', 'heure_debut', 'salle', 'id_pfe', 'id_jury1', 'id_jury2'];
}
