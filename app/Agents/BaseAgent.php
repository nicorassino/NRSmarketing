<?php

namespace App\Agents;

use App\Agents\Contracts\AgentInterface;
use App\Agents\Contracts\AgentResult;
use App\Models\AgentExecution;
use App\Models\CampaignRun;
use App\Services\AI\GeminiService;
use App\Services\Budget\BudgetService;
use App\Services\Context\ContextManager;
use Illuminate\Support\Facades\Log;

abstract class BaseAgent implements AgentInterface
{
    public function __construct(
        protected GeminiService $gemini,
        protected ContextManager $contextManager,
        protected BudgetService $budgetService,
    ) {}

    abstract public function getType(): string;
    abstract public function getRequiredContextFiles(): array;
    abstract public function getOutputSteps(): array;
    abstract protected function process(CampaignRun $run, array $context): AgentResult;

    public function execute(CampaignRun $run, array $context = []): AgentResult
    {
        // Check budget before executing
        if ($this->budgetService->isExceeded()) {
            return AgentResult::failure(
                'Presupuesto mensual agotado. No se puede ejecutar el agente.',
                'BUDGET_EXCEEDED'
            );
        }

        // Create execution record
        $execution = AgentExecution::create([
            'campaign_run_id' => $run->id,
            'agent_type' => $this->getType(),
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            // Load required context files
            $contextData = $this->loadRequiredContext($run);
            $context = array_merge($context, $contextData);

            // Execute the agent's specific logic
            $result = $this->process($run, $context);

            // Update execution record
            $execution->update([
                'status' => $result->success ? 'completed' : 'failed',
                'output_result' => $result->message,
                'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens,
                'cost_usd' => $result->costUsd,
                'completed_at' => now(),
                'duration_seconds' => now()->diffInSeconds($execution->started_at),
                'error_details' => $result->error ? ['error' => $result->error] : null,
            ]);

            if ($result->success) {
                Log::info("Agent [{$this->getType()}] completed successfully for run #{$run->id}");
            } else {
                Log::warning("Agent [{$this->getType()}] failed for run #{$run->id}: {$result->error}");
            }

            return $result;

        } catch (\Throwable $e) {
            $execution->update([
                'status' => 'failed',
                'error_details' => [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ],
                'completed_at' => now(),
                'duration_seconds' => now()->diffInSeconds($execution->started_at),
            ]);

            Log::error("Agent [{$this->getType()}] threw exception for run #{$run->id}: {$e->getMessage()}");

            return AgentResult::failure(
                "Error en agente {$this->getType()}: {$e->getMessage()}",
                $e->getMessage()
            );
        }
    }

    protected function loadRequiredContext(CampaignRun $run): array
    {
        $context = [];
        foreach ($this->getRequiredContextFiles() as $step) {
            $content = $this->contextManager->readContextFile($run, $step);
            if ($content !== null) {
                $context[$step] = $content;
            }
        }
        return $context;
    }

    protected function getConfig(string $key = null, $default = null)
    {
        $configKey = "agents.{$this->getType()}";
        if ($key) {
            $configKey .= ".{$key}";
        }
        return config($configKey, $default);
    }
}
