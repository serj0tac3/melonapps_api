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
        Schema::create('card_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_set_id')->constrained('card_sets')->cascadeOnDelete();
            
            // Identificador absoluto del sistema (Ej: "one-piece-OP08-001_p1")
            $table->string('unique_id')->unique(); 
            $table->string('card_number'); // El número físico impreso (Ej: "OP08-001")
            
            // 🚀 EL CORAZÓN AGNÓSTICO DE LA INTERFAZ
            // Aquí se guarda el JSON con estadísticas únicas de cada TCG (Coste, Vida, HP, Color, Energía...)
            $table->json('attributes')->nullable(); 
            
            $table->timestamps();

            $table->index('card_number'); // Indexamos para optimizar el buscador global
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_templates');
    }
};
