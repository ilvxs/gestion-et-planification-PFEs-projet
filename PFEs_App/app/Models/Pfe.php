<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pfe extends Model
{
    protected $primaryKey = 'id_pfe';
    
    protected $fillable = ['sujet', 'langue', 'id_etudiant', 'id_encadrant'];
}
