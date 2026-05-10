<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Etudiant extends Model
{
    protected $table = 'etudiants';
    protected $primaryKey = 'id_etudiant';

    protected $fillable = [
        'nom',
        'prenom',
        'cne',
        'email',
        'filiere'
    ];

    public function pfe()
    {
        return $this->hasOne(Pfe::class, 'id_etudiant', 'id_etudiant');
    }
}