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
        Schema::create('import_rejections', function (Blueprint $table) {
            $table->id();
            $table->string('game_slug'); // Ej: 'one-piece'
            $table->string('card_number')->nullable(); // Ej: 'OP08-999'
            $table->string('attempted_set_code')->nullable(); // Ej: 'OP-08'
            
            // El motivo exacto por el que falló
            $table->text('error_details'); 
            
            // Todo el objeto JSON de la carta para no perder la información
            $table->json('raw_data'); 
            
            // Para que en el futuro puedas marcar desde Angular si ya la arreglaste
            $table->boolean('is_resolved')->default(false); 
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_rejections');
    }
};
