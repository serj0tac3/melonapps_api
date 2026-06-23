<?php

namespace App\Console\Commands\TCG;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use App\Models\Game;
use App\Models\Region;
use App\Models\CardSet;
use App\Models\CardTemplate;
use App\Models\CardTemplateTranslation;
use App\Models\ImportRejection;

class ImportOnePieceCards extends Command
{
    // Firma del comando: ruta del archivo y código de región (ej. en, fr, jp)
    protected $signature = ' {filepath} {region=en}';
    protected $description = 'Importa cartas masivas de One Piece al Source of Truth con soporte multilingüe, protección de atributos y enrutamiento dinámico de Sets.';

    public function handle()
    {
        $filepath = $this->argument('filepath');
        $regionCode = strtolower($this->argument('region'));

        if (!File::exists($filepath)) {
            $this->error("❌ El archivo no existe: {$filepath}");
            return;
        }

        // 1. Cargar dependencias base
        $game = Game::where('slug', 'one-piece')->first();
        $region = Region::where('code', $regionCode)->first();

        if (!$game || !$region) {
            $this->error("❌ Juego 'one-piece' o región '{$regionCode}' no encontrados en la BD. Asegúrate de ejecutar los seeders primero.");
            return;
        }

        $this->info("📦 Leyendo archivo JSON masivo...");
        $cards = json_decode(File::get($filepath), true);
        
        $stats = ['inserted' => 0, 'rejected' => 0];
        $this->output->progressStart(count($cards));

        foreach ($cards as $key => $cardData) {
            
            $attemptedSetCode = $cardData['set_code'] ?? null;
            $set = null;

            // 2. ENRUTAMIENTO DINÁMICO (Inversión de Prioridad)

            // ✨ PASO 1: PRIORIDAD MÁXIMA - Búsqueda por Nombre Oficial Exacto
            // Ideal para productos especiales (ej. "Offline Regional Champion Card Set 2025 Vol.2")
            if (!empty($cardData['set_name'])) {
                $set = CardSet::where('game_id', $game->id)
                    ->whereHas('translations', function($query) use ($cardData) {
                        $query->where('name', $cardData['set_name']);
                    })->first();
            }

            // ✨ PASO 2: FALLBACK - Si no hay caja con ese nombre exacto, usamos el código matemático
            if (!$set) {
                // EL TRADUCTOR MÁGICO V2 (Para Cajas con corchetes [OP14-EB04])
                if (!empty($cardData['set_name']) && preg_match('/[\[【]([A-Za-z]+)-?(\d+)/u', $cardData['set_name'], $matches)) {
                    $attemptedSetCode = strtoupper($matches[1]) . '-' . $matches[2];
                }

                // Validación contra el Source of Truth (Tu BD) mediante el código
                $set = CardSet::where('game_id', $game->id)->where('code', $attemptedSetCode)->first();
            }

            // DEBUG TEMPORAL — quitar después
            if (in_array($cardData['id'] ?? '', ['EB04-027', 'EB04-028'])) {
                $this->warn("DEBUG [{$cardData['id']}]");
                $this->line("  set_name raw: " . ($cardData['set_name'] ?? 'NULL'));
                $this->line("  set_code raw: " . ($cardData['set_code'] ?? 'NULL'));
                $this->line("  attemptedSetCode: " . ($attemptedSetCode ?? 'NULL'));
                $this->line("  Set Encontrado: " . ($set ? $set->code : 'NO'));
            }

            // 3. RECHAZO SI NO SE ENCUENTRA LA COLECCIÓN
            if (!$set) {
                $rejectionCode = $attemptedSetCode ?? 'Desconocido';
                $rejectionReason = "No se encontró colección por nombre exacto ('{$cardData['set_name']}') ni por código ('{$rejectionCode}').";
                
                $this->rejectCard($game->slug, $cardData, $rejectionCode, $rejectionReason);
                $stats['rejected']++;
                $this->output->progressAdvance();
                continue;
            }

            // 4. INSERCIÓN CORE (Card Template) - Lógica de Idioma Base (Camino B)
            $template = CardTemplate::where('unique_id', $cardData['unique_id'])->first();

            $attributesData = [
                'cost'      => $cardData['cost'],
                'power'     => $cardData['power'],
                'life'      => $cardData['life'],
                'category'  => $cardData['category'],
                'rarity'    => $cardData['rarity'],
                'color'     => $cardData['color'],
                'attribute' => $cardData['attribute'],
                'counter'   => $cardData['counter'],
                'feature'   => $cardData['feature'],
            ];

            if (!$template) {
                // Si la carta no existe, la creamos desde cero con los atributos actuales
                $template = CardTemplate::create([
                    'unique_id'   => $cardData['unique_id'],
                    'card_set_id' => $set->id,
                    'card_number' => $cardData['id'],
                    'attributes'  => $attributesData
                ]);
            } elseif ($regionCode === 'en') {
                // Si ya existe, SOLO sobrescribimos los atributos universales si estamos importando el inglés
                $template->update(['attributes' => $attributesData]);
            }

            // 5. INSERCIÓN LOCALIZADA (Traducciones)
            CardTemplateTranslation::updateOrCreate(
                ['card_template_id' => $template->id, 'region_id' => $region->id],
                [
                    'name'      => $cardData['name'],
                    'effect'    => $cardData['effect'],
                    'image_url' => $cardData['image_url'],
                ]
            );

            $stats['inserted']++;
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        // 6. AUTO-CÁLCULO DEL TOTAL DE CARTAS
        $this->info("🔄 Recalculando contadores dinámicos de colecciones...");
        $allSets = CardSet::where('game_id', $game->id)->get();
        foreach ($allSets as $set) {
            $total = $set->templates()->count();
            $set->update(['total_cards' => $total]);
        }

        // 7. REPORTE FINAL
        $this->info("✅ Importación finalizada para la región: [{$regionCode}]");
        $this->line("🟢 Cartas procesadas/actualizadas: {$stats['inserted']}");
        if ($stats['rejected'] > 0) {
            $this->warn("🔴 Cartas enviadas al Limbo: {$stats['rejected']} (Revisa storage/logs/imports.log o la tabla import_rejections)");
        }
    }

    /**
     * Gestiona la Cola de Mensajes Muertos (Dead Letter Queue)
     */
    private function rejectCard($gameSlug, $cardData, $attemptedSetCode, $errorMessage)
    {
        // Guardar en Base de Datos para futura UI
        ImportRejection::create([
            'game_slug'          => $gameSlug,
            'card_number'        => $cardData['id'] ?? null,
            'attempted_set_code' => $attemptedSetCode,
            'error_details'      => $errorMessage,
            'raw_data'           => $cardData,
        ]);

        // Guardar en Log de archivo limpio
        $logMessage = "CARTA RECHAZADA (" . strtoupper($gameSlug) . ")\n"
                    . "Card Number: " . ($cardData['id'] ?? 'Desconocido') . "\n"
                    . "Attempted Set: " . ($attemptedSetCode ?? 'Ninguno') . "\n"
                    . "Error: {$errorMessage}\n"
                    . "-----------------------------------------------------------------------------------------";
        
        Log::channel('imports')->warning($logMessage);
    }
}