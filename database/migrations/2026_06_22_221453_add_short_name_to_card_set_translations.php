<?php
// database/migrations/xxxx_add_short_name_to_card_set_translations.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_set_translations', function (Blueprint $table) {
            // Nullable para no romper registros existentes antes de ejecutar el comando
            // Se rellena con data:sanitize-sets
            $table->string('short_name')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('card_set_translations', function (Blueprint $table) {
            $table->dropColumn('short_name');
        });
    }
};