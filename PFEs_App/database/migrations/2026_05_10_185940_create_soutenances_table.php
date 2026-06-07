<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
        public function up(): void
    {
        Schema::create('soutenances', function (Blueprint $table) {
            $table->id('id_soutenance'); // Clé primaire
            $table->date('date_soutenance');
            $table->time('heure_debut');
            $table->string('salle');

            // Clé étrangère 1 : PFE (UNIQUE)
            $table->unsignedBigInteger('id_pfe')->unique();
            $table->foreign('id_pfe')->references('id_pfe')->on('pfes')->onDelete('cascade');

            // Clé étrangère 2 : Jury 1 (Professeur)
            $table->unsignedBigInteger('id_jury1');
            $table->foreign('id_jury1')->references('id_professeur')->on('professeurs')->onDelete('restrict');

            // Clé étrangère 3 : Jury 2 (Professeur)
            $table->unsignedBigInteger('id_jury2');
            $table->foreign('id_jury2')->references('id_professeur')->on('professeurs')->onDelete('restrict');

            $table->timestamps();
            $table->unique(['date_soutenance', 'heure_debut', 'salle'], 'unique_soutenance_schedule');
        });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('soutenances');
    }
};
