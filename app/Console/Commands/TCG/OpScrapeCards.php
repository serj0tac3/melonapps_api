<?php

namespace App\Console\Commands\TCG;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\DomCrawler\Crawler;

class OpScrapeCards extends Command
{
    protected $signature = 'op:scrape-cards {region : jp, asia-en, asia-tc, asia-th, en, fr, o all}';
    protected $description = 'Motor Universal Blindado: Extracción en Cascada (.getInfo + .seriesName)';

    protected $regionsConfig = [
        'jp'      => 'www.onepiece-cardgame.com',
        'asia-tc' => 'asia-tc.onepiece-cardgame.com',
        'asia-en' => 'asia-en.onepiece-cardgame.com',
        'asia-th' => 'asia-th.onepiece-cardgame.com',
        'en'      => 'en.onepiece-cardgame.com',
        'fr'      => 'fr.onepiece-cardgame.com',
    ];

    private function getChromePath()
    {
        $paths = [
            'C:\Program Files\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/snap/bin/chromium',
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        return null; 
    }

    public function handle()
    {
        $regionArg = $this->argument('region');
        
        $regionsToRun = [];
        if ($regionArg === 'all') {
            $regionsToRun = array_keys($this->regionsConfig);
        } elseif (isset($this->regionsConfig[$regionArg])) {
            $regionsToRun = [$regionArg];
        } else {
            $this->error("❌ Región no válida. Usa: " . implode(', ', array_keys($this->regionsConfig)));
            return;
        }

        $chromePath = $this->getChromePath();
        if ($chromePath) {
            $this->info("⚙️  Navegador detectado automáticamente en: " . $chromePath);
        }

        foreach ($regionsToRun as $regionCode) {
            $domain = $this->regionsConfig[$regionCode];
            $this->info("🌍 INICIANDO EXTRACCIÓN: Región [{$regionCode}] -> {$domain}");

            $this->line("   🔍 Leyendo el menú de expansiones de Bandai...");
            $seriesMap = $this->fetchSeriesMap($domain, $chromePath);

            if (empty($seriesMap)) {
                $this->error("   ❌ No se encontraron expansiones para {$regionCode}.");
                continue;
            }

            $this->info("   ✅ Se han descubierto " . count($seriesMap) . " expansiones. Empezando descarga...");

            $allCardsData = [];
            $jsonFileName = "one_piece_{$regionCode}.json";

            if (Storage::disk('local')->exists($jsonFileName)) {
                $allCardsData = json_decode(Storage::disk('local')->get($jsonFileName), true) ?? [];
            }

            $index = 1;
            foreach ($seriesMap as $seriesId => $seriesName) {
                $this->warn("📦 PROCESANDO: {$seriesName} ({$index}/" . count($seriesMap) . ")");
                
                $colorsToSearch = ['']; 
                
                try {
                    $testUrl = "https://{$domain}/cardlist/?series={$seriesId}";
                    
                    $browser = Browsershot::url($testUrl)
                        ->noSandbox()
                        ->userAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
                        ->waitUntil('networkidle2')
                        ->timeout(120);
                    
                    if ($chromePath) $browser->setChromePath($chromePath);
                    
                    $testHtml = $browser->bodyHtml();
                        
                    if (str_contains($testHtml, 'Too many search results') || str_contains($testHtml, 'too many')) {
                        $this->warn("      ⚠️ Colección masiva detectada. Dividiendo por colores...");
                        $colorsToSearch = ['1', '2', '3', '4', '5', '6'];
                    }
                } catch (\Exception $e) {
                    $this->error("      ❌ Error de comprobación: " . $e->getMessage());
                    continue;
                }

                foreach ($colorsToSearch as $colorCode) {
                    $page = 1;
                    $previousState = ''; 

                    while (true) {
                        $colorParam = $colorCode !== '' ? "&color={$colorCode}" : '';
                        $currentUrl = "https://{$domain}/cardlist/?series={$seriesId}{$colorParam}&page={$page}";
                        $this->line("      📄 Leyendo pág {$page}" . ($colorCode !== '' ? " (Color {$colorCode})" : "") . "...");

                        $html = null;
                        $maxRetries = 3;
                        $success = false;

                        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                            try {
                                $browserLoop = Browsershot::url($currentUrl)
                                    ->noSandbox()
                                    ->userAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
                                    ->waitUntil('networkidle2')
                                    ->timeout(120);
                                
                                if ($chromePath) $browserLoop->setChromePath($chromePath);
                                
                                $html = $browserLoop->bodyHtml();
                                $success = true;
                                break; 
                            } catch (\Exception $e) {
                                if ($attempt === $maxRetries) {
                                    $this->error("      ❌ Fallo tras 3 intentos en pág {$page}: " . $e->getMessage());
                                } else {
                                    $this->warn("      ⚠️ Corte de Bandai. Reintentando en 5s (Intento {$attempt}/{$maxRetries})...");
                                    sleep(5);
                                }
                            }
                        }

                        if (!$success) break; 

                        $crawler = new Crawler($html);
                        $cartasEnEstaPagina = []; 
                        
                        // Iteramos sobre las cartas visibles
                        $crawler->filter('.resultCol > dl, .resultCol > div.modalOpen')->each(function (Crawler $node) use (&$allCardsData, &$cartasEnEstaPagina, $seriesName, $domain, $crawler) {
                            try {
                                $cardNumber = $node->filter('.infoCol span')->count() > 0 ? trim($node->filter('.infoCol span')->text()) : null;
                                if (!$cardNumber) return;

                                // Extraer UniqueID usando la imagen visible
                                $visibleImg = $node->filter('img')->first();
                                $imageRelativeUrl = $visibleImg->count() > 0 ? ($visibleImg->attr('data-src') ?: $visibleImg->attr('src')) : '';
                                $imageRelativeUrl = explode('?', $imageRelativeUrl)[0];
                                $filename = basename(parse_url($imageRelativeUrl, PHP_URL_PATH));
                                $uniqueId = preg_replace('/\.[^.]+$/', '', $filename);
                                if (empty($uniqueId)) $uniqueId = $cardNumber;

                                $cartasEnEstaPagina[] = $uniqueId;

                                // 🎯 EL BUSCADOR DE MODALES (Basado en tu descubrimiento)
                                $modalTarget = ltrim($node->attr('data-target') ?: '', '#');
                                $modalNode = null;

                                // Buscamos por data-target ("modal_EB04-028_p1") o directamente por ID ("EB04-028_p1")
                                if ($modalTarget && $crawler->filter("#{$modalTarget}")->count() > 0) {
                                    $modalNode = $crawler->filter("#{$modalTarget}")->first();
                                } elseif ($crawler->filter("#{$uniqueId}")->count() > 0) {
                                    $modalNode = $crawler->filter("#{$uniqueId}")->first();
                                }

                                // La fuente de información prioritaria siempre será el Modal oculto si lo encontramos
                                $infoSource = $modalNode ?: $node;

                                // Extraemos la mejor imagen posible (del modal .frontCol si existe)
                                $imgNode = $infoSource->filter('.cardImg img, .frontCol img')->count() > 0 ? $infoSource->filter('.cardImg img, .frontCol img')->first() : $infoSource->filter('img')->first();
                                $finalImageUrl = $imgNode->count() > 0 ? ($imgNode->attr('data-src') ?: $imgNode->attr('src')) : '';
                                $finalImageUrl = explode('?', $finalImageUrl)[0];

                                $infoParts = explode('|', $infoSource->filter('.infoCol')->count() > 0 ? $infoSource->filter('.infoCol')->text() : '');
                                $hasLife = $infoSource->filter('.life')->count() > 0;
                                $isLeader = $hasLife;

                                $rawName = $infoSource->filter('.cardName')->count() > 0 ? $infoSource->filter('.cardName')->text() : 'Unknown';
                                $cleanName = html_entity_decode(trim($rawName), ENT_QUOTES, 'UTF-8');

                                $cost = null;
                                if (!$isLeader && $infoSource->filter('.cost')->count() > 0) {
                                    if (preg_match('/\d+/', $infoSource->filter('.cost')->text(), $matches)) $cost = (int)$matches[0];
                                }
                                $power = null;
                                if ($infoSource->filter('.power')->count() > 0) {
                                    if (preg_match('/\d+/', $infoSource->filter('.power')->text(), $matches)) $power = (int)$matches[0];
                                }
                                $life = null;
                                if ($hasLife) {
                                    if (preg_match('/\d+/', $infoSource->filter('.life')->text(), $matches)) $life = (int)$matches[0];
                                }

                                // ✨ LA ESTRATEGIA EN CASCADA PARA EL NOMBRE DE COLECCIÓN ✨
                                $specificSetName = null;

                                // 1. PASO 1 (Tu ruta para Promos/Paralelas): Buscamos el div .getInfo
                                if ($infoSource->filter('.getInfo')->count() > 0) {
                                    $seriesHtml = $infoSource->filter('.getInfo')->html();
                                    // Separamos por la etiqueta de cierre del título </h3>
                                    $parts = preg_split('/<\/h3>/i', $seriesHtml);
                                    if (count($parts) > 1 && !empty(trim(strip_tags($parts[1])))) {
                                        $specificSetName = trim(strip_tags($parts[1]));
                                    } else {
                                        $specificSetName = trim(strip_tags($parts[0]));
                                    }
                                } 
                                // 2. PASO 2 (La ruta clásica para Sobres): Buscamos el div .seriesName
                                elseif ($infoSource->filter('.seriesName')->count() > 0) {
                                    $seriesHtml = $infoSource->filter('.seriesName')->html();
                                    // Separamos por el salto de línea <br>
                                    $parts = preg_split('/<br\s*\/?>/i', $seriesHtml);
                                    if (count($parts) > 1 && !empty(trim(strip_tags($parts[1])))) {
                                        $specificSetName = trim(strip_tags($parts[1]));
                                    } else {
                                        $specificSetName = trim(strip_tags($parts[0]));
                                        $specificSetName = trim(str_replace(['Card Set(s)', 'Série(s)', 'シリーズ', 'カードセット'], '', $specificSetName));
                                    }
                                }

                                // 3. EL SALVAVIDAS: Si Bandai falla en ambas plantillas, usamos el menú
                                $finalSetName = (!empty($specificSetName) && strtolower($specificSetName) !== 'other product card') 
                                    ? $specificSetName 
                                    : $seriesName;

                                // --- LÓGICA DE LIMPIEZA DEL SET CODE ---
                                $cleanSetCode = null;
                                $idParts = explode('-', $cardNumber);
                                $prefix = $idParts[0] ?? '';

                                if ($prefix === 'P' || $prefix === 'PR') {
                                    $cleanSetCode = 'PROMO';
                                } elseif (preg_match('/^([a-zA-Z]+)(\d+)$/', $prefix, $m)) {
                                    $cleanSetCode = strtoupper($m[1]) . '-' . $m[2];
                                } else {
                                    if (preg_match('/[\[【](.*?)[\]】]/u', $finalSetName, $m)) {
                                        $cleanSetCode = $m[1];
                                    }
                                }

                                $allCardsData[$uniqueId] = [
                                    'unique_id' => $uniqueId,
                                    'id'        => $cardNumber,
                                    'set_code'  => $cleanSetCode, 
                                    'name'      => $cleanName,
                                    'set_name'  => $finalSetName,
                                    'image_url' => str_starts_with($finalImageUrl, 'http') ? $finalImageUrl : "https://{$domain}/" . ltrim(str_replace('../', '', $finalImageUrl), '/'),
                                    'cost'      => $cost,
                                    'power'     => $power,
                                    'life'      => $life,
                                    'category'  => isset($infoParts[2]) ? trim($infoParts[2]) : ($infoSource->filter('.category')->count() > 0 ? trim($infoSource->filter('.category')->text()) : null),
                                    'rarity'    => isset($infoParts[1]) ? trim($infoParts[1]) : ($infoSource->filter('.rarity')->count() > 0 ? trim($infoSource->filter('.rarity')->text()) : null),
                                    'color'     => $infoSource->filter('.color')->count() > 0 ? trim(str_replace(['Color', 'Couleur', '色'], '', $infoSource->filter('.color')->text())) : null,
                                    'attribute' => $infoSource->filter('.attribute')->count() > 0 ? trim(str_replace(['Attribute', 'Attribut', '属性'], '', $infoSource->filter('.attribute')->text())) : null,
                                    'counter'   => $infoSource->filter('.counter')->count() > 0 ? trim(str_replace(['Counter', 'Contre', 'カウンター'], '', $infoSource->filter('.counter')->text())) : null,
                                    'feature'   => $infoSource->filter('.feature')->count() > 0 ? trim(str_replace(['Type', '特徴'], '', $infoSource->filter('.feature')->text())) : null,
                                    'effect'    => $infoSource->filter('.text')->count() > 0 ? trim(str_replace(['Effect', 'Effet', 'テキスト'], '', $infoSource->filter('.text')->text())) : null,
                                ];
                            } catch (\Exception $e) {}
                        });

                        $currentState = implode(',', $cartasEnEstaPagina);
                        if (empty($cartasEnEstaPagina) || $currentState === $previousState) {
                            break; 
                        }
                        $previousState = $currentState;

                        Storage::disk('local')->put($jsonFileName, json_encode($allCardsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        
                        $page++;
                        sleep(1);
                    }
                }
                $index++;
            }
            $this->newLine();
            $this->info("🎉 Región {$regionCode} FINALIZADA.");
        }
    }

    private function fetchSeriesMap($domain, $chromePath)
    {
        $seriesMap = [];
        $url = "https://{$domain}/cardlist/";

        try {
            $browserMenu = Browsershot::url($url)
                ->noSandbox()
                ->userAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
                ->waitUntil('networkidle2')
                ->timeout(120);
            
            if ($chromePath) $browserMenu->setChromePath($chromePath);
            
            $html = $browserMenu->bodyHtml();

            $crawler = new Crawler($html);
            $crawler->filter('li.selModalClose, select[name="series"] option')->each(function (Crawler $node) use (&$seriesMap) {
                $val = $node->attr('data-value') ?: $node->attr('value');
                if (is_numeric($val) && $val > 0) {
                    $name = trim(preg_replace('/\s+/', ' ', $node->text()));
                    $seriesMap[$val] = $name;
                }
            });
        } catch (\Exception $e) {
            $this->error("💥 Error obteniendo el menú: " . $e->getMessage());
        }
        
        return $seriesMap;
    }
}