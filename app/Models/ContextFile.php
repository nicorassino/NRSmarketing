<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContextFile extends Model
{
    protected $fillable = [
        'campaign_run_id',
        'step',
        'file_path',
        'format',
        'summary',
    ];

    public function campaignRun(): BelongsTo
    {
        return $this->belongsTo(CampaignRun::class);
    }

    public function getFullPathAttribute(): string
    {
        return base_path($this->file_path);
    }

    public function getContent(): string|array|null
    {
        $path = $this->full_path;

        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);

        if ($this->format === 'json') {
            return json_decode($content, true);
        }

        return $content;
    }
}
