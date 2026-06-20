<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_template_id')->constrained('card_templates')->cascadeOnDelete();
            $table->timestamps();

            // Un usuario solo puede desear una plantilla específica una vez
            $table->unique(['user_id', 'card_template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};