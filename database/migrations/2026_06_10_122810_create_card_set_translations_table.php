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
        Schema::create('card_set_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_set_id')->constrained('card_sets')->cascadeOnDelete();
            $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
            
            // Datos específicos del idioma indexados para filtros
            $table->string('name'); // Ej: "Two Legends" en EN / "二つの伝説" en JP
            $table->date('release_date')->nullable(); // Las fechas cambian según el país
            $table->string('image_url')->nullable(); // Logo o banner localizado
            $table->timestamps();

            // Evita que una expansión tenga dos traducciones para el mismo idioma
            $table->unique(['card_set_id', 'region_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_set_translations');
    }
};
