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
        Schema::create('user_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('card_template_id')->constrained('card_templates')->cascadeOnDelete();
            
            // Datos específicos de la copia que posee el usuario
            $table->integer('quantity')->default(1);
            $table->boolean('is_foil')->default(false); // ¿Es la versión brillante/holográfica?
            
            $table->timestamps();

            // Evitamos que haya dos filas idénticas de la misma carta (normal o foil) para el mismo usuario.
            // Si consigue otra, simplemente sumaremos +1 a la columna 'quantity'
            $table->unique(['user_id', 'card_template_id', 'is_foil']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_cards');
    }
};
