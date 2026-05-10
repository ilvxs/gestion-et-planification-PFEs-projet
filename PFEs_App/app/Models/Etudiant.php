<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Etudiant extends Model
{
    // Tells Laravel to use 'id_etudiant' instead of 'id'
    protected $primaryKey = 'id_etudiant'; 

    // (Optional but recommended) Allow mass assignment for your columns
    protected $fillable = ['nom', 'prenom', 'cne', 'email', 'filiere']; 
}