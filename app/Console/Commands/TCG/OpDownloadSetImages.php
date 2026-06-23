<?php

namespace App\Console\Commands\TCG;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\CardSetTranslation;

class OpDownloadSetImages extends Command
{
    protected $signature   = 'op:download-set-images {--force : Sobrescribe las imágenes aunque ya existan}';
    protected $description = 'Comprueba si las imágenes existen en local; si no, las descarga y recorta.';

    public function handle(): int
    {
        $this->info('🖼  Iniciando comprobación y descarga de carátulas...');

        $this->ensureStorageLink();

        $nodePath = config('scraper.node_script_path')
            ? dirname(config('scraper.node_script_path')) . '/auto-recortar.js'
            : base_path('../cardmarket-scraper/auto-recortar.js');

        // Nos traemos TODAS las traducciones que tengan algo en image_url, sean http o no
        $translations = CardSetTranslation::with(['cardSet', 'region'])->whereNotNull('image_url')->get();

        if ($translations->isEmpty()) {
            $this->info('✅ La base de datos no tiene ninguna imagen registrada.');
            return self::SUCCESS;
        }

        $bar      = $this->output->createProgressBar($translations->count());
        $errores  = 0;
        $ok       = 0;
        $saltados = 0;

        $bar->start();

        foreach ($translations as $translation) {
            try {
                $status = $this->processSetImage($translation, $nodePath, $this->option('force'));
                
                if ($status === true) {
                    $ok++;
                } elseif ($status === false) {
                    $saltados++; // Ya existía en disco
                }
            } catch (\Throwable $e) {
                $errores++;
                $this->newLine();
                $setCode = $translation->cardSet?->code ?? 'Desconocido';
                $this->error("❌ Error en [{$setCode}]: " . $e->getMessage());
                Log::error('op:download-set-images failed', [
                    'set_code' => $setCode,
                    'error'    => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        
        $this->info("✨ Completado. Descargadas: {$ok} | Ya existían en local: {$saltados} | Errores: {$errores}");

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function processSetImage(CardSetTranslation $translation, string $nodePath, bool $force): bool
    {
        $imageUrl = $translation->image_url;
        $set      = $translation->cardSet;

        if (!$set) {
            throw new \Exception("La traducción no tiene un Set asociado.");
        }

        // 1. Calculamos cómo debe llamarse el archivo en local
        $cleanCode   = Str::slug($set->code); // OP-15 -> op-15
        $regionStr   = $translation->region?->code ?? 'en';
        
        // Extraer la extensión original o usar webp por defecto
        $extension   = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'webp';
        
        $directory   = 'sets';
        $filename    = "{$cleanCode}_{$regionStr}.{$extension}";
        $storagePath = "{$directory}/{$filename}";

        // 2. ¿LA IMAGEN YA EXISTE FÍSICAMENTE EN LOCAL?
        if (!$force && Storage::disk('public')->exists($storagePath)) {
            // Si ya existe y la BD seguía apuntando al http, lo corregimos en silencio
            if (Str::startsWith($imageUrl, ['http://', 'https://'])) {
                $translation->update(['image_url' => "/storage/{$storagePath}"]);
            }
            return false; // Saltado
        }

        // 3. LA IMAGEN NO EXISTE. ¿Tenemos una URL de internet para bajarla?
        if (!Str::startsWith($imageUrl, ['http://', 'https://'])) {
            throw new \Exception("Falta el archivo local y la BD no tiene un enlace 'http' para descargarlo (Tiene: '{$imageUrl}').");
        }

        // 4. DESCARGAMOS LA IMAGEN DE INTERNET
        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        $response = Http::retry(3, 2000)->timeout(30)->get($imageUrl);

        if ($response->status() === 429) {
            $this->newLine();
            $this->warn("⏳ Limitación de descargas. Esperando 30 segundos...");
            sleep(30);
            throw new \Exception("Rate Limit alcanzado.");
        }

        if (!$response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()} al descargar la imagen.");
        }

        Storage::disk('public')->put($storagePath, $response->body());

        // 5. RECORTAMOS
        if (file_exists($nodePath)) {
            $absolutePath = Storage::disk('public')->path($storagePath);
            $resultado = Process::timeout(30)->run(
                "node " . escapeshellarg($nodePath) . " " . escapeshellarg($absolutePath)
            );

            if (!$resultado->successful()) {
                $this->newLine();
                $this->warn("⚠️  No se pudo recortar [{$set->code}]: " . $resultado->errorOutput());
            }
        }

        // 6. ACTUALIZAMOS LA BD CON LA NUEVA RUTA LOCAL
        $translation->update(['image_url' => "/storage/{$storagePath}"]);

        sleep(1); // Pausa de cortesía con el servidor de origen

        return true;
    }

    private function ensureStorageLink(): void
    {
        $linkPath = public_path('storage');
        if (!file_exists($linkPath)) {
            $this->callSilent('storage:link');
        }
    }
}