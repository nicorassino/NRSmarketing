<?php

namespace App\Agents;

use App\Agents\Contracts\AgentResult;
use App\Models\CampaignRun;
use App\Models\Prospect;
use App\Services\Search\SerpApiService;

class ScoutAgent extends BaseAgent
{
    public function __construct(
        \App\Services\AI\GeminiService $gemini,
        \App\Services\Context\ContextManager $contextManager,
        \App\Services\Budget\BudgetService $budgetService,
        protected SerpApiService $serpApi,
    ) {
        parent::__construct($gemini, $contextManager, $budgetService);
    }

    public function getType(): string
    {
        return 'scout';
    }

    public function getRequiredContextFiles(): array
    {
        return ['01_product_analysis', '02_scout_mission'];
    }

    public function getOutputSteps(): array
    {
        return ['03_search_results'];
    }

    protected function process(CampaignRun $run, array $context): AgentResult
    {
        $mission = $context['02_scout_mission'] ?? null;
        $campaign = $run->campaign;
        $overrides = (array) ($run->metadata['scout_overrides'] ?? []);

        if (empty($mission)) {
            return AgentResult::failure(
                'No se encontró la misión de búsqueda.',
                'MISSION_NOT_FOUND'
            );
        }

        $location = trim((string) ($overrides['location'] ?? '')) ?: ($campaign->target_location ?? '');
        if (empty($location)) {
            return AgentResult::failure(
                'No se puede ejecutar Scout sin target_location definido en la campaña.',
                'TARGET_LOCATION_REQUIRED'
            );
        }

        if (empty($campaign->target_niche) && empty($campaign->objective)) {
            return AgentResult::failure(
                'No se puede ejecutar Scout sin target_niche u objective definido.',
                'TARGETING_REQUIRED'
            );
        }

        $analysis = $context['01_product_analysis'] ?? '';

        // Step 1: Use Gemini Flash to extract search queries from the mission
        $queriesResponse = $this->gemini->generate(
            prompt: $this->buildQueryExtractionPrompt($mission),
            model: config('agents.scout.model'),
            maxTokens: 1024,
            temperature: 0.3,
        );

        if (!$queriesResponse['success']) {
            return AgentResult::failure(
                'Error al generar queries de búsqueda.',
                $queriesResponse['error'] ?? 'Unknown'
            );
        }

        $queries = $this->parseSearchQueries($queriesResponse['content']);

        // Step 2: Execute searches via SerpAPI
        $hl = strtolower((string) ($overrides['hl'] ?? 'es'));
        $gl = strtolower((string) ($overrides['gl'] ?? 'ar'));
        $num = (int) ($overrides['num'] ?? config('agents.scout.max_results_per_search', 20));
        $num = max(1, min($num, 100));
        $allResults = [];
        $searchRequests = [];
        $mapsEnabled = (bool) config('agents.scout.maps_enabled', true);
        $mapsSearches = (int) config('agents.scout.maps_searches', 3);
        $mapsSearches = max(0, min($mapsSearches, 10));

        $maxSearchesCfg = (int) ($overrides['max_searches'] ?? config('agents.scout.max_searches', 10));
        $maxSearchesCfg = max(1, min($maxSearchesCfg, 25));
        $maxSearches = min(count($queries), $maxSearchesCfg);

        for ($i = 0; $i < $maxSearches; $i++) {
            if ($this->budgetService->isExceeded()) {
                break;
            }

            $searchResults = $this->serpApi->search($queries[$i], [
                'location' => $location,
                'hl' => $hl,
                'gl' => $gl,
                'num' => $num,
            ]);

            $searchRequests[] = [
                'query' => $queries[$i],
                'params' => [
                    'engine' => 'google',
                    'location' => $location,
                    'hl' => $hl,
                    'gl' => $gl,
                    'num' => $num,
                ],
            ];

            if (!empty($searchResults)) {
                $allResults = array_merge($allResults, $searchResults);
            }

            if ($mapsEnabled && $i < $mapsSearches) {
                $mapsResults = $this->serpApi->searchMaps($queries[$i], $location);
                if (!empty($mapsResults)) {
                    $allResults = array_merge($allResults, $mapsResults);
                }

                $searchRequests[] = [
                    'query' => $queries[$i],
                    'params' => [
                        'engine' => 'google_maps',
                        'location' => $location,
                        'hl' => $hl,
                    ],
                ];
            }

            $this->budgetService->logUsage(
                service: 'serpapi',
                operation: 'search',
                relatedType: get_class($campaign),
                relatedId: $campaign->id,
                metadata: ['query' => $queries[$i]],
            );
        }

        $totalRawBeforeDedup = count($allResults);

        // Step 3: Deduplicate + merge results preserving contacts
        $allResults = $this->deduplicateAndMergeResults($allResults);
        $mergedResults = $allResults;

        // Step 4: Use Gemini Flash to analyze and score prospects
        $scoredResults = $this->scoreProspects($mergedResults, $analysis, $mission);

        // Step 5: Save prospects to database
        $savedCount = 0;
        foreach ($scoredResults as $result) {
            Prospect::create([
                'campaign_run_id' => $run->id,
                'company_name' => $result['company_name'] ?? $result['title'] ?? 'Desconocido',
                'contact_name' => $result['contact_name'] ?? null,
                'email' => $result['email'] ?? null,
                'phone' => $result['phone'] ?? null,
                'website_url' => $result['url'] ?? null,
                'instagram_handle' => $result['instagram'] ?? null,
                'source' => 'serpapi',
                'raw_data' => $result,
                'ai_analysis' => $result['analysis'] ?? null,
                'score' => $result['score'] ?? 50,
                'status' => 'new',
            ]);
            $savedCount++;
        }

        // Step 6: Save context file
        $this->contextManager->writeContextFile(
            $run,
            '03_search_results',
            [
                'queries_used' => array_slice($queries, 0, $maxSearches),
                'search_requests' => $searchRequests,
                'total_raw_results' => $totalRawBeforeDedup,
                'total_after_merge' => count($mergedResults),
                'total_scored' => count($scoredResults),
                'total_saved' => $savedCount,
                'merged_results' => $mergedResults,
                'results' => $scoredResults,
            ]
        );

        $totalInputTokens = ($queriesResponse['input_tokens'] ?? 0);
        $totalOutputTokens = ($queriesResponse['output_tokens'] ?? 0);
        $totalCost = ($queriesResponse['cost_usd'] ?? 0);

        return AgentResult::success(
            message: "Scout encontró {$savedCount} prospectos de {$maxSearches} búsquedas.",
            data: [
                'queries' => array_slice($queries, 0, $maxSearches),
                'total_results' => $savedCount,
            ],
            inputTokens: $totalInputTokens,
            outputTokens: $totalOutputTokens,
            costUsd: $totalCost,
        );
    }

    private function buildQueryExtractionPrompt(string $mission): string
    {
        return <<<PROMPT
Analizá la siguiente misión de búsqueda y generá una lista de queries de búsqueda para Google.

MISIÓN:
{$mission}

REGLAS:
- Generá entre 3 y 10 queries diferentes
- Cada query debe ser específica y orientada a encontrar prospectos
- Incluí variaciones geográficas si corresponde
- Usá operadores de búsqueda cuando sea útil (site:, intitle:, etc.)
- Respondé SOLO con las queries, una por línea, sin numeración ni formato extra
PROMPT;
    }

    private function parseSearchQueries(string $content): array
    {
        $lines = array_filter(
            array_map('trim', explode("\n", $content)),
            fn($line) => !empty($line) && !str_starts_with($line, '#') && !str_starts_with($line, '-')
        );

        // Clean up numbering if present
        return array_values(array_map(function ($line) {
            return preg_replace('/^\d+[\.\)]\s*/', '', $line);
        }, $lines));
    }

    private function deduplicateAndMergeResults(array $results): array
    {
        $mergedByDomain = [];

        foreach ($results as $result) {
            $domain = $this->resolveDomain($result);
            if (!isset($mergedByDomain[$domain])) {
                $mergedByDomain[$domain] = $result;
                $mergedByDomain[$domain]['source_list'] = [$result['source'] ?? 'unknown'];
                $mergedByDomain[$domain]['contact_richness'] = $this->contactRichness($mergedByDomain[$domain]);
                continue;
            }

            $base = $mergedByDomain[$domain];
            $candidate = $result;
            $sourceList = $base['source_list'] ?? [];
            $sourceList[] = $candidate['source'] ?? 'unknown';

            // Prefer map-based contact data when available.
            foreach (['phone', 'email', 'instagram', 'address', 'contact_name'] as $field) {
                if (empty($base[$field]) && !empty($candidate[$field])) {
                    $base[$field] = $candidate[$field];
                }
            }

            if (empty($base['url']) && !empty($candidate['url'])) {
                $base['url'] = $candidate['url'];
            }

            if (empty($base['snippet']) && !empty($candidate['snippet'])) {
                $base['snippet'] = $candidate['snippet'];
            }

            // Keep highest apparent relevance if available.
            $baseScore = (int) ($base['score'] ?? 0);
            $candidateScore = (int) ($candidate['score'] ?? 0);
            if ($candidateScore > $baseScore) {
                $base['score'] = $candidateScore;
                if (!empty($candidate['analysis'])) {
                    $base['analysis'] = $candidate['analysis'];
                }
            }

            $base['source_list'] = array_values(array_unique($sourceList));
            $base['contact_richness'] = $this->contactRichness($base);
            $mergedByDomain[$domain] = $base;
        }

        $merged = array_values($mergedByDomain);

        // Sort by "contact richness" first, then by explicit score.
        usort($merged, function (array $a, array $b): int {
            $contactA = (int) ($a['contact_richness'] ?? $this->contactRichness($a));
            $contactB = (int) ($b['contact_richness'] ?? $this->contactRichness($b));
            if ($contactA !== $contactB) {
                return $contactB <=> $contactA;
            }

            return ((int) ($b['score'] ?? 0)) <=> ((int) ($a['score'] ?? 0));
        });

        foreach ($merged as &$item) {
            $item['contact_richness'] = (int) ($item['contact_richness'] ?? $this->contactRichness($item));
        }
        unset($item);

        return $merged;
    }

    private function resolveDomain(array $result): string
    {
        $url = $result['url'] ?? $result['link'] ?? '';
        if (!empty($url)) {
            $host = parse_url($url, PHP_URL_HOST);
            if (!empty($host)) {
                return strtolower((string) $host);
            }
        }

        $title = trim((string) ($result['company_name'] ?? $result['title'] ?? 'sin-dominio'));
        return 'name:' . strtolower($title);
    }

    private function contactRichness(array $result): int
    {
        $score = 0;
        if (!empty($result['phone'])) {
            $score += 3;
        }
        if (!empty($result['email'])) {
            $score += 3;
        }
        if (!empty($result['instagram'])) {
            $score += 2;
        }
        if (!empty($result['address'])) {
            $score += 1;
        }

        return $score;
    }

    private function scoreProspects(array $results, string $analysis, string $mission): array
    {
        if (empty($results)) {
            return [];
        }

        // Batch the results for AI scoring
        $resultsJson = json_encode(array_slice($results, 0, 50), JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
Analizá estos resultados de búsqueda y asigná un puntaje de relevancia (0-100) a cada uno.

ANÁLISIS DEL PRODUCTO:
{$analysis}

MISIÓN DE BÚSQUEDA:
{$mission}

RESULTADOS:
{$resultsJson}

Para cada resultado, respondé en formato JSON array con estos campos:
- title: nombre de la empresa/institución
- company_name: nombre limpio de la empresa
- url: URL del sitio
- score: puntaje 0-100
- analysis: breve explicación de por qué este puntaje (1 línea)
- contact_name: si encontrás algún nombre de contacto
- email: si encontrás algún email
- phone: si encontrás algún teléfono

Respondé SOLO con el JSON array, sin markdown ni texto adicional.
PROMPT;

        $response = $this->gemini->generate(
            prompt: $prompt,
            model: config('agents.scout.model'),
            maxTokens: 4096,
            temperature: 0.3,
        );

        if (!$response['success']) {
            // Return results without scoring if AI fails
            return array_map(function ($r) {
                $r['score'] = $r['score'] ?? 50;
                $r['analysis'] = $r['analysis'] ?? 'Sin análisis';
                $r['contact_richness'] = (int) ($r['contact_richness'] ?? $this->contactRichness($r));
                return $r;
            }, $results);
        }

        $scored = json_decode($response['content'], true);

        if (!is_array($scored)) {
            // Try to extract JSON from response
            if (preg_match('/\[.*\]/s', $response['content'], $matches)) {
                $scored = json_decode($matches[0], true);
            }
        }

        if (!is_array($scored)) {
            return $results;
        }

        // Merge Gemini scoring output back with original contacts to avoid losing data.
        $index = [];
        foreach ($results as $original) {
            $key = $this->resolveDomain($original);
            $index[$key] = $original;
        }

        $final = [];
        foreach ($scored as $item) {
            if (!is_array($item)) {
                continue;
            }

            $key = $this->resolveDomain($item);
            $original = $index[$key] ?? [];
            $merged = array_merge($original, $item);

            foreach (['phone', 'email', 'instagram', 'address', 'contact_name', 'url', 'source_list'] as $field) {
                if (empty($merged[$field]) && !empty($original[$field])) {
                    $merged[$field] = $original[$field];
                }
            }
            $merged['contact_richness'] = (int) ($original['contact_richness'] ?? $this->contactRichness($merged));

            $final[] = $merged;
        }

        if (empty($final)) {
            return $results;
        }

        return $final;
    }
}
