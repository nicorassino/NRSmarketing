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

        if (empty($mission)) {
            return AgentResult::failure(
                'No se encontró la misión de búsqueda.',
                'MISSION_NOT_FOUND'
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
        $campaign = $run->campaign;
        $location = $campaign->target_location ?? 'Argentina';
        $allResults = [];

        $maxSearches = min(count($queries), config('agents.scout.max_searches', 10));

        for ($i = 0; $i < $maxSearches; $i++) {
            if ($this->budgetService->isExceeded()) {
                break;
            }

            $searchResults = $this->serpApi->search($queries[$i], [
                'location' => $location,
                'num' => config('agents.scout.max_results_per_search', 20),
            ]);

            if (!empty($searchResults)) {
                $allResults = array_merge($allResults, $searchResults);
            }

            $this->budgetService->logUsage(
                service: 'serpapi',
                operation: 'search',
                relatedType: get_class($campaign),
                relatedId: $campaign->id,
                metadata: ['query' => $queries[$i]],
            );
        }

        // Step 3: Deduplicate results
        $allResults = $this->deduplicateResults($allResults);

        // Step 4: Use Gemini Flash to analyze and score prospects
        $scoredResults = $this->scoreProspects($allResults, $analysis, $mission);

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
                'total_raw_results' => count($allResults),
                'total_scored' => count($scoredResults),
                'total_saved' => $savedCount,
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

    private function deduplicateResults(array $results): array
    {
        $seen = [];
        $unique = [];

        foreach ($results as $result) {
            $key = $result['url'] ?? $result['link'] ?? $result['title'] ?? '';
            $domain = parse_url($key, PHP_URL_HOST) ?? $key;

            if (!isset($seen[$domain])) {
                $seen[$domain] = true;
                $unique[] = $result;
            }
        }

        return $unique;
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
            return array_map(fn($r) => array_merge($r, ['score' => 50, 'analysis' => 'Sin análisis']), $results);
        }

        $scored = json_decode($response['content'], true);

        if (!is_array($scored)) {
            // Try to extract JSON from response
            if (preg_match('/\[.*\]/s', $response['content'], $matches)) {
                $scored = json_decode($matches[0], true);
            }
        }

        return is_array($scored) ? $scored : $results;
    }
}
