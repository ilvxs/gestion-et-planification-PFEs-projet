<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
{
    Schema::create('etudiants', function (Blueprint $table) {
        $table->id('id_etudiant'); // Clé primaire
        $table->string('nom');
        $table->string('prenom');
        $table->string('cne')->unique(); // Contrainte UNIQUE selon le MLD
        $table->string('email')->unique(); // Contrainte UNIQUE selon le MLD
        $table->string('filiere');
        $table->timestamps();
    });
}

    public function down(): void
    {
        Schema::dropIfExists('etudiants');
    }
};
