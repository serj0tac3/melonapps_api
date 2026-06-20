<?php

namespace App\Console\Commands\TCG;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class OpExtractSets extends Command
{
    // Comando para ejecutarlo: php artisan op:extract-sets en
    protected $signature = 'op:extract-sets {region=en}';
    protected $description = 'Lee el JSON de cartas y genera un listado de colecciones únicas para la base de datos';

    public function handle()
    {
        $region = $this->argument('region');
        $jsonFileName = "one_piece_{$region}.json";

        if (!Storage::disk('local')->exists($jsonFileName)) {
            $this->error("❌ No se ha encontrado el archivo {$jsonFileName}. Ejecuta primero el scraper.");
            return;
        }

        $this->info("🔍 Leyendo cartas de {$jsonFileName}...");
        $cards = json_decode(Storage::disk('local')->get($jsonFileName), true);
        
        $uniqueSets = [];

        foreach ($cards as $card) {
            $setName = $card['set_name'] ?? 'Unknown Set';
            $setCode = $card['set_code'] ?? 'UNKNOWN';

            // Creamos un identificador único basado en el nombre para no repetir
            $hash = md5($setName);

            if (!isset($uniqueSets[$hash])) {
                
                // Calculamos la familia dinámicamente
                $family = 'OTHER';
                if ($setCode === 'PROMO') {
                    $family = 'PR'; // Familia para promocionales
                } else {
                    // Si el código es "OP-01", la familia es "OP"
                    $parts = explode('-', $setCode);
                    if (count($parts) > 1) {
                        $family = strtoupper($parts[0]);
                    }
                }

                // Construimos la estructura exacta que usa tu base de datos
                $uniqueSets[$hash] = [
                    'code' => $setCode,
                    'family' => $family,
                    'translations' => [
                        'en' => [
                            'name' => $setName,
                            'release_date' => null, // Para rellenar manualmente si se desea
                            'image_url' => null
                        ]
                    ]
                ];
            }
        }

        // Ordenamos alfabéticamente por familia y luego por código para que quede bonito
        usort($uniqueSets, function ($a, $b) {
            if ($a['family'] === $b['family']) {
                return strcmp($a['code'], $b['code']);
            }
            return strcmp($a['family'], $b['family']);
        });

        $outputFileName = "one_piece_sets_extracted_{$region}.json";
        Storage::disk('local')->put($outputFileName, json_encode(array_values($uniqueSets), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->newLine();
        $this->info("🎉 ¡Extracción completada!");
        $this->info("📁 Se han encontrado " . count($uniqueSets) . " colecciones únicas.");
        $this->info("💾 Guardado en: storage/app/{$outputFileName}");
    }
}