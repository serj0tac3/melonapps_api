<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use App\Models\Game;
use App\Models\Region;
use App\Models\CardSet;
use App\Models\CardSetTranslation;

class CardSetSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Aseguramos que el Juego Universal existe
        $game = Game::firstOrCreate(
            ['slug' => 'one-piece'],
            ['name' => 'One Piece Card Game']
        );

        // 2. Localizamos el JSON del Diccionario Maestro
        $jsonPath = database_path('data/one_piece_sets.json');
        
        if (!File::exists($jsonPath)) {
            $this->command->error("❌ No se encontró el Diccionario en: {$jsonPath}");
            return;
        }

        $sets = json_decode(File::get($jsonPath), true);
        $this->command->info("📦 Procesando " . count($sets) . " colecciones maestras...");

        // 3. Inyectamos los datos en la estructura Híbrida
        foreach ($sets as $setData) {
            
            // Creamos o actualizamos la Entidad Universal (CardSet)
            $cardSet = CardSet::updateOrCreate(
                ['game_id' => $game->id, 'code' => $setData['code']],
                ['family' => $setData['family']]
                // Nota: total_cards no se toca, se queda en su default (0)
            );

            // 4. Procesamos la Localización (Translations)
            foreach ($setData['translations'] as $regionCode => $translation) {
                
                // Aseguramos que la Región existe (ej: "en", "es", "jp")
                $region = Region::firstOrCreate(
                    ['code' => $regionCode],
                    ['name' => strtoupper($regionCode)] 
                );

                // Insertamos la traducción atada a la Colección y a la Región
                CardSetTranslation::updateOrCreate(
                    ['card_set_id' => $cardSet->id, 'region_id' => $region->id],
                    [
                        'name'         => $translation['name'],
                        'release_date' => empty($translation['release_date']) ? null : $translation['release_date'],
                        'image_url'    => $translation['image_url'] ?? null,
                    ]
                );
            }
        }

        $this->command->info("✅ ¡Diccionario Maestro inyectado perfectamente en la Base de Datos!");
    }
}