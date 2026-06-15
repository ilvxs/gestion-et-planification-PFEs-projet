<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salles', function (Blueprint $table) {
            $table->id('id_salle');
            $table->string('nom')->unique();
            $table->boolean('disponible')->default(true);
            $table->timestamps();
        });

        $this->insertDefaultSalles();

        Schema::table('soutenances', function (Blueprint $table) {
            $table->unsignedBigInteger('id_salle')->nullable()->after('heure_debut');
        });

        $this->backfillSallesFromSoutenances();

        Schema::table('soutenances', function (Blueprint $table) {
            $table->dropUnique('unique_soutenance_schedule');
            $table
                ->foreign('id_salle')
                ->references('id_salle')
                ->on('salles')
                ->restrictOnDelete();
            $table->unique(['date_soutenance', 'heure_debut', 'id_salle'], 'unique_soutenance_schedule');
        });

        Schema::table('soutenances', function (Blueprint $table) {
            $table->dropColumn('salle');
        });
    }

    public function down(): void
    {
        Schema::table('soutenances', function (Blueprint $table) {
            $table->string('salle')->nullable()->after('heure_debut');
        });

        DB::table('soutenances')
            ->leftJoin('salles', 'soutenances.id_salle', '=', 'salles.id_salle')
            ->update([
                'soutenances.salle' => DB::raw('salles.nom'),
            ]);

        Schema::table('soutenances', function (Blueprint $table) {
            $table->dropUnique('unique_soutenance_schedule');
            $table->dropForeign(['id_salle']);
            $table->dropColumn('id_salle');
            $table->unique(['date_soutenance', 'heure_debut', 'salle'], 'unique_soutenance_schedule');
        });

        Schema::dropIfExists('salles');
    }

    private function insertDefaultSalles(): void
    {
        $now = now();

        $salles = [
            'Salle 4 AB',
            'Salle 5 AB',
            'Salle 16 AB',
            'Salle 17 AB',
            'Amphi A',
            'Amphi B',
        ];

        foreach ($salles as $salle) {
            DB::table('salles')->insertOrIgnore([
                'nom' => $salle,
                'disponible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function backfillSallesFromSoutenances(): void
    {
        $now = now();

        $sallesExistantes = DB::table('soutenances')
            ->whereNotNull('salle')
            ->where('salle', '<>', '')
            ->distinct()
            ->pluck('salle');

        foreach ($sallesExistantes as $salle) {
            DB::table('salles')->insertOrIgnore([
                'nom' => $salle,
                'disponible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('soutenances')
            ->join('salles', 'soutenances.salle', '=', 'salles.nom')
            ->update([
                'soutenances.id_salle' => DB::raw('salles.id_salle'),
            ]);
    }
};
