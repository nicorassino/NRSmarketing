<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prospect extends Model
{
    protected $fillable = [
        'campaign_run_id',
        'company_name',
        'contact_name',
        'email',
        'phone',
        'website_url',
        'instagram_handle',
        'source',
        'raw_data',
        'ai_analysis',
        'status',
        'selected_channel',
        'score',
    ];

    protected $casts = [
        'raw_data' => 'array',
    ];

    const STATUS_NEW = 'new';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CONTACTED = 'contacted';
    const STATUS_CONVERTED = 'converted';

    public function campaignRun(): BelongsTo
    {
        return $this->belongsTo(CampaignRun::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ProspectMessage::class);
    }

    public function latestMessage()
    {
        return $this->hasOne(ProspectMessage::class)->latestOfMany();
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_NEW => 'Nuevo',
            self::STATUS_APPROVED => 'Aprobado',
            self::STATUS_REJECTED => 'Rechazado',
            self::STATUS_CONTACTED => 'Contactado',
            self::STATUS_CONVERTED => 'Convertido',
            default => $this->status,
        };
    }

    public function scopeNew($query)
    {
        return $query->where('status', self::STATUS_NEW);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }
}
