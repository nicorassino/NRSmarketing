<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignRun extends Model
{
    protected $fillable = [
        'campaign_id',
        'run_number',
        'status',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function agentExecutions(): HasMany
    {
        return $this->hasMany(AgentExecution::class);
    }

    public function contextFiles(): HasMany
    {
        return $this->hasMany(ContextFile::class);
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class);
    }

    public function getContextPath(): string
    {
        return sprintf(
            '%s/campaign_%03d/run_%03d',
            config('agents.context.base_path'),
            $this->campaign_id,
            $this->run_number
        );
    }
}
