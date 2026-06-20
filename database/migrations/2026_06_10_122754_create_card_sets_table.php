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
        Schema::create('card_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->string('code'); // Identificador estricto de Bandai/Pokémon (Ej: "OP-08", "SV01")
            $table->string('family')->nullable(); // Agrupador visual (Ej: "OP", "ST", "EB")
            $table->integer('total_cards')->default(0); // Contador interno automático
            $table->timestamps();

            // Evita por completo colecciones duplicadas dentro del mismo juego
            $table->unique(['game_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_sets');
    }
};
