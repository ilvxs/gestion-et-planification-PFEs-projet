<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Salle extends Model
{
    protected $table = 'salles';
    protected $primaryKey = 'id_salle';

    protected $fillable = [
        'nom',
        'disponible',
    ];

    protected $casts = [
        'disponible' => 'boolean',
    ];

    public function soutenances()
    {
        return $this->hasMany(Soutenance::class, 'id_salle', 'id_salle');
    }
}
