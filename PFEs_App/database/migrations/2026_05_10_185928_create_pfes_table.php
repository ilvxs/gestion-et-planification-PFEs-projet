<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('pfes', function (Blueprint $table) {
        $table->id('id_pfe'); // Clé primaire
        $table->string('sujet');
        $table->string('langue');
        
        // Clé étrangère 1 : Etudiant (UNIQUE car un PFE = 1 étudiant selon le MLD)
        $table->unsignedBigInteger('id_etudiant')->unique(); 
        $table->foreign('id_etudiant')->references('id_etudiant')->on('etudiants')->onDelete('cascade');

        // Clé étrangère 2 : Encadrant (Professeur)
        $table->unsignedBigInteger('id_encadrant')->nullable();
        $table->foreign('id_encadrant')->references('id_professeur')->on('professeurs')->onDelete('restrict');
        
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pves');
    }
};
