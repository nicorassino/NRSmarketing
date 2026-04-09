<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentExecution extends Model
{
    protected $fillable = [
        'campaign_run_id',
        'agent_type',
        'status',
        'input_prompt',
        'output_result',
        'input_tokens',
        'output_tokens',
        'cost_usd',
        'duration_seconds',
        'error_details',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'error_details' => 'array',
        'cost_usd' => 'decimal:4',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function campaignRun(): BelongsTo
    {
        return $this->belongsTo(CampaignRun::class);
    }

    public function getTotalTokensAttribute(): int
    {
        return $this->input_tokens + $this->output_tokens;
    }
}
