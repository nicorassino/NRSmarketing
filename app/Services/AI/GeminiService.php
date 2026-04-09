<?php

namespace App\Services\AI;

use App\Models\ApiUsageLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key', env('GEMINI_API_KEY', ''));
    }

    /**
     * Generate content using Gemini API.
     */
    public function generate(
        string $prompt,
        ?string $model = null,
        int $maxTokens = 4096,
        float $temperature = 0.7,
        ?string $systemPrompt = null,
    ): array {
        $model = $model ?? config('agents.chat.model', 'gemini-2.5-flash');

        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'error' => 'GEMINI_API_KEY no configurada en .env',
            ];
        }

        $contents = [];

        if ($systemPrompt) {
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => "System: {$systemPrompt}"]],
            ];
            $contents[] = [
                'role' => 'model',
                'parts' => [['text' => 'Entendido. Procedo según las instrucciones.']],
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $prompt]],
        ];

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => $maxTokens,
                'temperature' => $temperature,
            ],
        ];

        try {
            $url = "{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}";

            $response = Http::timeout(120)
                ->post($url, $payload);

            if (!$response->successful()) {
                Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => "HTTP {$response->status()}: {$response->body()}",
                ];
            }

            $data = $response->json();

            // Extract content
            $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Extract token counts
            $usageMetadata = $data['usageMetadata'] ?? [];
            $inputTokens = $usageMetadata['promptTokenCount'] ?? 0;
            $outputTokens = $usageMetadata['candidatesTokenCount'] ?? 0;

            // Calculate cost
            $costUsd = $this->calculateCost($model, $inputTokens, $outputTokens);

            return [
                'success' => true,
                'content' => $content,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_usd' => $costUsd,
                'model' => $model,
            ];

        } catch (\Throwable $e) {
            Log::error('Gemini API exception', [
                'message' => $e->getMessage(),
                'model' => $model,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate content with chat history (for Global Chat).
     */
    public function chat(
        array $messages,
        ?string $model = null,
        int $maxTokens = 4096,
        float $temperature = 0.7,
        ?string $systemPrompt = null,
    ): array {
        $model = $model ?? config('agents.chat.model', 'gemini-2.5-flash');

        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'error' => 'GEMINI_API_KEY no configurada en .env',
            ];
        }

        $contents = [];

        if ($systemPrompt) {
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => "System: {$systemPrompt}"]],
            ];
            $contents[] = [
                'role' => 'model',
                'parts' => [['text' => 'Entendido. Procedo según las instrucciones.']],
            ];
        }

        foreach ($messages as $msg) {
            $role = $msg['role'] === 'assistant' ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $msg['content']]],
            ];
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => $maxTokens,
                'temperature' => $temperature,
            ],
        ];

        try {
            $url = "{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}";

            $response = Http::timeout(120)
                ->post($url, $payload);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => "HTTP {$response->status()}: {$response->body()}",
                ];
            }

            $data = $response->json();
            $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $usageMetadata = $data['usageMetadata'] ?? [];
            $inputTokens = $usageMetadata['promptTokenCount'] ?? 0;
            $outputTokens = $usageMetadata['candidatesTokenCount'] ?? 0;
            $costUsd = $this->calculateCost($model, $inputTokens, $outputTokens);

            return [
                'success' => true,
                'content' => $content,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_usd' => $costUsd,
                'model' => $model,
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate API cost in USD.
     */
    private function calculateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        if (str_contains($model, 'pro')) {
            $inputRate = config('agents.pricing.gemini_pro_input_per_1m', 1.25);
            $outputRate = config('agents.pricing.gemini_pro_output_per_1m', 10.00);
        } else {
            $inputRate = config('agents.pricing.gemini_flash_input_per_1m', 0.15);
            $outputRate = config('agents.pricing.gemini_flash_output_per_1m', 0.60);
        }

        return ($inputTokens / 1_000_000 * $inputRate) + ($outputTokens / 1_000_000 * $outputRate);
    }
}
