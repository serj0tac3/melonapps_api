<?php

namespace App\Console\Commands\TCG;

use App\Models\CardSet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SanitizeCardSets extends Command
{
    protected $signature   = 'data:sanitize-sets {--dry-run : Muestra los cambios sin aplicarlos}';
    protected $description = 'Normaliza códigos de card_sets y nombres de card_set_translations';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('🔍 MODO DRY-RUN — No se modificará nada en la base de datos.');
        }

        $this->sanitizeCodes($isDryRun);
        $this->sanitizeNames($isDryRun);

        $this->newLine();
        $this->info('✅ Proceso completado.');

        return self::SUCCESS;
    }

    private function sanitizeCodes(bool $isDryRun): void
    {
        $this->newLine();
        $this->info('📦 Normalizando códigos de card_sets...');

        $sets = CardSet::where('code', 'LIKE', '%-%')->get();

        if ($sets->isEmpty()) {
            $this->line('   ℹ️  No hay códigos con guion. Ya están normalizados.');
            return;
        }

        $bar = $this->output->createProgressBar($sets->count());
        $bar->start();

        DB::transaction(function () use ($sets, $isDryRun, $bar) {
            foreach ($sets as $set) {
                $originalCode   = $set->code;
                $normalizedCode = str_replace('-', '', $originalCode);

                if (!$isDryRun) {
                    $set->update(['code' => $normalizedCode]);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("   ✅ {$sets->count()} códigos normalizados.");
    }

    private function sanitizeNames(bool $isDryRun): void
    {
        $this->newLine();
        $this->info('🏷️  Generando short_name en card_set_translations...');

        $prefixes = [
            'BOOSTER PACK -',
            'STARTER DECK -',
            'STARTER DECK EX -',
            'ULTRA DECK -',
            'EXTRA BOOSTER -',
            'PREMIUM BOOSTER -',
            'MEMORIAL COLLECTION -',
            'SPECIAL GOODS SET -',
        ];

        $translations = DB::table('card_set_translations')
            ->whereNull('short_name')
            ->get();

        if ($translations->isEmpty()) {
            $this->line('   ℹ️  Todos los registros ya tienen short_name.');
            return;
        }

        $bar = $this->output->createProgressBar($translations->count());
        $bar->start();

        DB::transaction(function () use ($translations, $prefixes, $isDryRun, $bar) {
            foreach ($translations as $translation) {
                $fullName  = $translation->name;
                $shortName = $this->extractShortName($fullName, $prefixes);

                if (!$isDryRun && $shortName !== $fullName) {
                    DB::table('card_set_translations')
                        ->where('id', $translation->id)
                        ->update(['short_name' => $shortName]);
                } else if (!$isDryRun) {
                    // Si no tiene prefijo, le copiamos el nombre original al short_name igualmente
                    DB::table('card_set_translations')
                        ->where('id', $translation->id)
                        ->update(['short_name' => $fullName]);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("   ✅ short_name generado para {$translations->count()} traducciones.");
    }

    private function extractShortName(string $fullName, array $prefixes): string
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with(strtoupper($fullName), strtoupper($prefix))) {
                return trim(substr($fullName, strlen($prefix)));
            }
        }
        return $fullName;
    }
}