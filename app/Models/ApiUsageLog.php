<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiUsageLog extends Model
{
    protected $fillable = [
        'service',
        'operation',
        'input_tokens',
        'output_tokens',
        'cost_usd',
        'related_type',
        'related_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'cost_usd' => 'decimal:6',
    ];

    /**
     * Get total cost for the current month.
     */
    public static function currentMonthCost(): float
    {
        return (float) static::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('cost_usd');
    }

    /**
     * Get cost breakdown by service for the current month.
     */
    public static function currentMonthBreakdown(): array
    {
        return static::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->selectRaw('service, SUM(cost_usd) as total_cost, COUNT(*) as total_calls')
            ->groupBy('service')
            ->get()
            ->keyBy('service')
            ->toArray();
    }

    /**
     * Check if budget is exceeded.
     */
    public static function isBudgetExceeded(): bool
    {
        $limit = config('agents.budget.monthly_limit_usd');
        $threshold = config('agents.budget.critical_threshold');
        return static::currentMonthCost() >= ($limit * $threshold);
    }

    /**
     * Check if budget warning threshold is reached.
     */
    public static function isBudgetWarning(): bool
    {
        $limit = config('agents.budget.monthly_limit_usd');
        $threshold = config('agents.budget.warning_threshold');
        return static::currentMonthCost() >= ($limit * $threshold);
    }

    /**
     * Get remaining budget for current month.
     */
    public static function remainingBudget(): float
    {
        return max(0, config('agents.budget.monthly_limit_usd') - static::currentMonthCost());
    }
}
