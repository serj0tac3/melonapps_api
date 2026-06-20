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
        Schema::create('card_template_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_template_id')->constrained('card_templates')->cascadeOnDelete();
            $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
            
            // Textos e imágenes localizadas
            $table->string('name'); // Nombre en el idioma de la región
            $table->text('effect')->nullable(); // Texto de habilidad traducido (Soporta line-clamp de UI)
            $table->string('image_url')->nullable(); // Ruta a la imagen física en ese idioma
            $table->timestamps();

            $table->unique(['card_template_id', 'region_id'], 'template_region_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_template_translations');
    }
};
