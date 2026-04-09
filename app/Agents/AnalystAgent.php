<?php

namespace App\Agents;

use App\Agents\Contracts\AgentResult;
use App\Models\CampaignRun;
use App\Models\Product;

class AnalystAgent extends BaseAgent
{
    public function getType(): string
    {
        return 'analyst';
    }

    public function getRequiredContextFiles(): array
    {
        return []; // Analyst is the first agent — no prior context needed
    }

    public function getOutputSteps(): array
    {
        return ['01_product_analysis', '02_scout_mission'];
    }

    protected function process(CampaignRun $run, array $context): AgentResult
    {
        $campaign = $run->campaign()->with('product.documents')->first();
        $product = $campaign->product;

        // Gather all product documentation text
        $documentationText = $this->gatherDocumentation($product);

        if (empty($documentationText)) {
            return AgentResult::failure(
                'No hay documentación disponible para analizar.',
                'NO_DOCUMENTATION'
            );
        }

        // Build the analysis prompt
        $prompt = $this->buildAnalysisPrompt($product, $documentationText, $campaign);

        // Call Gemini 2.5 Pro (deep analysis model)
        $response = $this->gemini->generate(
            prompt: $prompt,
            model: $this->getConfig('model'),
            maxTokens: $this->getConfig('max_tokens'),
            temperature: $this->getConfig('temperature'),
            systemPrompt: $this->getConfig('system_prompt'),
        );

        if (!$response['success']) {
            return AgentResult::failure(
                'Error al comunicarse con Gemini API.',
                $response['error'] ?? 'Unknown error'
            );
        }

        // Parse the AI response into analysis + mission
        $parsed = $this->parseAnalysisResponse($response['content']);

        // Save context files
        $this->contextManager->writeContextFile(
            $run,
            '01_product_analysis',
            $parsed['analysis']
        );

        $this->contextManager->writeContextFile(
            $run,
            '02_scout_mission',
            $parsed['mission']
        );

        // Update product with extracted insights
        $product->update([
            'pain_points_summary' => $parsed['pain_points'] ?? null,
            'value_proposition' => $parsed['value_proposition'] ?? null,
            'is_analyzed' => true,
            'analyzed_at' => now(),
        ]);

        // Log API usage
        $this->budgetService->logUsage(
            service: 'gemini_pro',
            operation: 'product_analysis',
            inputTokens: $response['input_tokens'] ?? 0,
            outputTokens: $response['output_tokens'] ?? 0,
            relatedType: get_class($campaign),
            relatedId: $campaign->id,
        );

        return AgentResult::success(
            message: 'Análisis completado. Misión de búsqueda generada.',
            data: $parsed,
            inputTokens: $response['input_tokens'] ?? 0,
            outputTokens: $response['output_tokens'] ?? 0,
            costUsd: $response['cost_usd'] ?? 0,
        );
    }

    private function gatherDocumentation(Product $product): string
    {
        $texts = [];

        // Product description
        if ($product->description) {
            $texts[] = "## Descripción del Producto\n{$product->description}";
        }

        // Uploaded documents
        foreach ($product->documents as $doc) {
            if ($doc->extracted_text) {
                $texts[] = "## Documento: {$doc->title}\n{$doc->extracted_text}";
            }
        }

        return implode("\n\n---\n\n", $texts);
    }

    private function buildAnalysisPrompt(Product $product, string $documentation, $campaign): string
    {
        $niche = $campaign->target_niche ? "Nicho objetivo: {$campaign->target_niche}" : '';
        $location = $campaign->target_location ? "Ubicación objetivo: {$campaign->target_location}" : '';

        return <<<PROMPT
# Tarea: Análisis de Producto y Generación de Misión de Búsqueda

## Producto: {$product->name}
{$niche}
{$location}

## Documentación del Producto:
{$documentation}

---

## Instrucciones:

Analizá la documentación del producto y generá DOS secciones claramente separadas:

### SECCIÓN 1: ANÁLISIS DEL PRODUCTO (marcá con <!-- ANALYSIS_START --> y <!-- ANALYSIS_END -->)
1. **Puntos de Dolor**: Listá los problemas específicos que resuelve el producto
2. **Propuesta de Valor**: ¿Qué hace único a este producto?
3. **Perfil de Cliente Ideal (ICP)**: ¿Quién necesita este producto?
4. **Keywords de Búsqueda**: Palabras clave para encontrar potenciales clientes
5. **Señales de Compra**: ¿Qué indicadores en un sitio web sugieren que necesitan este producto?

### SECCIÓN 2: MISIÓN DE BÚSQUEDA (marcá con <!-- MISSION_START --> y <!-- MISSION_END -->)
Redactá un prompt detallado para el Agente Scout que incluya:
1. Qué tipo de empresas/instituciones buscar
2. En qué ubicación geográfica
3. Qué señales buscar en sus sitios web
4. Cómo priorizar los resultados
5. Criterios de descarte

Sé específico y orientado a la acción. El Scout necesita instrucciones claras.
PROMPT;
    }

    private function parseAnalysisResponse(string $content): array
    {
        $analysis = $content;
        $mission = '';
        $painPoints = '';
        $valueProposition = '';

        // Extract analysis section
        if (preg_match('/<!-- ANALYSIS_START -->(.*?)<!-- ANALYSIS_END -->/s', $content, $matches)) {
            $analysis = trim($matches[1]);
        }

        // Extract mission section
        if (preg_match('/<!-- MISSION_START -->(.*?)<!-- MISSION_END -->/s', $content, $matches)) {
            $mission = trim($matches[1]);
        } else {
            // If no markers, use the second half as mission
            $parts = preg_split('/#{2,3}\s*(?:SECCIÓN\s*2|Misión|MISIÓN)/i', $content, 2);
            if (count($parts) === 2) {
                $analysis = trim($parts[0]);
                $mission = trim($parts[1]);
            }
        }

        // Extract pain points
        if (preg_match('/(?:Puntos de Dolor|Pain Points)[:\s]*(.*?)(?=\n#{1,3}|\n\*\*|$)/si', $analysis, $matches)) {
            $painPoints = trim($matches[1]);
        }

        // Extract value proposition
        if (preg_match('/(?:Propuesta de Valor|Value Proposition)[:\s]*(.*?)(?=\n#{1,3}|\n\*\*|$)/si', $analysis, $matches)) {
            $valueProposition = trim($matches[1]);
        }

        return [
            'analysis' => $analysis ?: $content,
            'mission' => $mission ?: 'Misión no generada. Revisar análisis.',
            'pain_points' => $painPoints,
            'value_proposition' => $valueProposition,
            'full_response' => $content,
        ];
    }
}
