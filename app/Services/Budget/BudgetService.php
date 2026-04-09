<?php

namespace App\Services\Budget;

use App\Models\ApiUsageLog;
use Illuminate\Support\Facades\Log;

class BudgetService
{
    /**
     * Log an API usage event and track costs.
     */
    public function logUsage(
        string $service,
        string $operation,
        int $inputTokens = 0,
        int $outputTokens = 0,
        ?string $relatedType = null,
        ?int $relatedId = null,
        array $metadata = [],
    ): ApiUsageLog {
        $costUsd = $this->calculateCost($service, $inputTokens, $outputTokens);

        $log = ApiUsageLog::create([
            'service' => $service,
            'operation' => $operation,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_usd' => $costUsd,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'metadata' => $metadata,
        ]);

        // Check and log warnings
        if (ApiUsageLog::isBudgetExceeded()) {
            Log::critical('NRSMarketing: ¡PRESUPUESTO MENSUAL AGOTADO!', [
                'month_cost' => ApiUsageLog::currentMonthCost(),
                'limit' => config('agents.budget.monthly_limit_usd'),
            ]);
        } elseif (ApiUsageLog::isBudgetWarning()) {
            Log::warning('NRSMarketing: Presupuesto al 80% o más', [
                'month_cost' => ApiUsageLog::currentMonthCost(),
                'remaining' => ApiUsageLog::remainingBudget(),
            ]);
        }

        return $log;
    }

    /**
     * Check if the monthly budget is exceeded.
     */
    public function isExceeded(): bool
    {
        return ApiUsageLog::isBudgetExceeded();
    }

    /**
     * Check if budget warning threshold is reached.
     */
    public function isWarning(): bool
    {
        return ApiUsageLog::isBudgetWarning();
    }

    /**
     * Get current month cost.
     */
    public function getCurrentMonthCost(): float
    {
        return ApiUsageLog::currentMonthCost();
    }

    /**
     * Get remaining budget.
     */
    public function getRemainingBudget(): float
    {
        return ApiUsageLog::remainingBudget();
    }

    /**
     * Get usage breakdown for current month.
     */
    public function getMonthlyBreakdown(): array
    {
        return ApiUsageLog::currentMonthBreakdown();
    }

    /**
     * Get budget summary for display.
     */
    public function getSummary(): array
    {
        $limit = config('agents.budget.monthly_limit_usd');
        $current = $this->getCurrentMonthCost();
        $remaining = max(0, $limit - $current);
        $percentage = $limit > 0 ? round(($current / $limit) * 100, 1) : 0;

        return [
            'limit_usd' => $limit,
            'current_usd' => round($current, 4),
            'remaining_usd' => round($remaining, 4),
            'percentage' => $percentage,
            'is_warning' => $this->isWarning(),
            'is_exceeded' => $this->isExceeded(),
            'breakdown' => $this->getMonthlyBreakdown(),
        ];
    }

    /**
     * Calculate cost for a specific service and token usage.
     */
    private function calculateCost(string $service, int $inputTokens, int $outputTokens): float
    {
        return match ($service) {
            'gemini_pro' => ($inputTokens / 1_000_000 * config('agents.pricing.gemini_pro_input_per_1m', 1.25))
                + ($outputTokens / 1_000_000 * config('agents.pricing.gemini_pro_output_per_1m', 10.00)),
            'gemini_flash' => ($inputTokens / 1_000_000 * config('agents.pricing.gemini_flash_input_per_1m', 0.15))
                + ($outputTokens / 1_000_000 * config('agents.pricing.gemini_flash_output_per_1m', 0.60)),
            'serpapi' => config('agents.pricing.serpapi_per_search', 0.01),
            default => 0,
        };
    }
}
