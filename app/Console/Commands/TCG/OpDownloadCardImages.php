<?php

namespace App\Console\Commands\TCG;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class OpDownloadCardImages extends Command
{
    protected $signature = 'op:download-card-images';
    protected $description = 'Descarga imágenes de cartas (incluyendo paralelas) en public y actualiza la BD';

    public function handle()
    {
        $this->info('🚀 Iniciando el Gestor de Descargas (Soporte para Paralelas activado)...');

        $files = Storage::disk('local')->files();
        $jsonFiles = array_filter($files, fn($f) => preg_match('/^one_piece_(.+)\.json$/', $f));

        if (empty($jsonFiles)) {
            $this->error('❌ No se han encontrado archivos JSON en storage/app/');
            return;
        }

        foreach ($jsonFiles as $file) {
            preg_match('/^one_piece_(.+)\.json$/', $file, $matches);
            $regionName = $matches[1];
            
            $this->newLine();
            $this->warn("🌍 Procesando Idioma: [ {$regionName} ]");

            $jsonContent = Storage::disk('local')->get($file);
            $cards = json_decode($jsonContent, true) ?? [];
            
            foreach ($cards as $card) {
                // 1. Validaciones iniciales
                if (empty($card['image_url']) || str_contains($card['image_url'], '/storage/cards/')) {
                    continue;
                }

                // VITAL: Usamos estrictamente el unique_id (ej: EB01-009_p1) para no pisar versiones paralelas
                $uniqueId = $card['unique_id'] ?? null;
                if (!$uniqueId) continue; 

                // 2. Preparar rutas
                $setCode = explode('-', $uniqueId)[0] ?? 'PROMO';
                $safeSetCode = trim(preg_replace('/[\\\\\/\:\*\?\"\<\>\|]/', '-', $setCode));
                
                $imagesPath = "cards/{$safeSetCode}/{$regionName}";
                Storage::disk('public')->makeDirectory($imagesPath);

                $url = $card['image_url'];
                $parsedPath = parse_url($url, PHP_URL_PATH);
                $fileName = basename($parsedPath);
                
                if (empty($fileName)) {
                    $extension = pathinfo($parsedPath, PATHINFO_EXTENSION) ?: 'png';
                    $fileName = "{$uniqueId}.{$extension}";
                }
                
                $imageFullPath = "{$imagesPath}/{$fileName}";
                $localDatabaseUrl = "/storage/{$imageFullPath}";

                // 3. Descargar imagen si no existe
                $downloadSuccess = true;
                if (!Storage::disk('public')->exists($imageFullPath)) {
                    try {
                        $response = Http::retry(3, 5000)
                            ->withHeaders([
                                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                            ])->timeout(30)->get($url);

                        if ($response->successful()) {
                            Storage::disk('public')->put($imageFullPath, $response->body());
                            usleep(250000); 
                            $this->line("      ⬇️ Descargada Paralela/Arte: {$fileName}");
                        } else {
                            $downloadSuccess = false;
                            $this->error("      ❌ Fallo HTTP al descargar {$fileName}");
                        }
                    } catch (\Exception $e) {
                        $downloadSuccess = false;
                        $this->error("      ❌ Error crítico en {$fileName}: " . $e->getMessage());
                    }
                } else {
                    $this->line("      ✅ Ya existe: {$fileName}");
                }

                // 4. ACTUALIZAR BASE DE DATOS (Estricto por versión paralela e idioma)
                if ($downloadSuccess) {
                    // Primero, buscamos el ID interno de la carta en la tabla principal
                    $template = DB::table('card_templates')->where('unique_id', $uniqueId)->first();

                    if ($template) {
                        // Segundo, actualizamos la tabla de traducciones (que es donde vive la imagen real)
                        DB::table('card_template_translations')
                            ->where('card_template_id', $template->id)
                            // Opcional pero recomendado: solo actualizamos si la URL vieja coincide, 
                            // para no pisar por accidente las imágenes de otros idiomas (ej. japonés)
                            ->where('image_url', $url) 
                            ->update(['image_url' => config('app.url') . $localDatabaseUrl]);
                    }
                }
            }
            $this->info("   ✅ Idioma {$regionName} sincronizado al 100%.");
        }
        
        $this->newLine();
        $this->info('🎉 ¡ÉXITO! Descargas y actualizaciones listas.');
        $this->warn('👉 IMPORTANTE: Ejecuta "php artisan storage:link" en tu terminal si no lo has hecho aún.');
    }
}