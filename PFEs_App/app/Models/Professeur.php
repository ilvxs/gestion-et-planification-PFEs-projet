<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Professeur extends Model
{
    protected $primaryKey = 'id_professeur';
    
    protected $fillable = ['nom', 'prenom', 'specialite'];
}