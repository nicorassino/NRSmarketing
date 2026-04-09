<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'objective',
        'target_niche',
        'target_location',
        'status',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_ANALYZING = 'analyzing';
    const STATUS_MISSION_REVIEW = 'mission_review';
    const STATUS_SCOUTING = 'scouting';
    const STATUS_INBOX_REVIEW = 'inbox_review';
    const STATUS_EXECUTING = 'executing';
    const STATUS_COMPLETED = 'completed';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(CampaignRun::class);
    }

    public function chatConversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class);
    }

    public function latestRun()
    {
        return $this->hasOne(CampaignRun::class)->latestOfMany();
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Borrador',
            self::STATUS_ANALYZING => 'Analizando...',
            self::STATUS_MISSION_REVIEW => 'Revisión de Misión',
            self::STATUS_SCOUTING => 'Buscando Prospectos...',
            self::STATUS_INBOX_REVIEW => 'Bandeja de Entrada',
            self::STATUS_EXECUTING => 'Enviando Mensajes...',
            self::STATUS_COMPLETED => 'Completada',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_ANALYZING, self::STATUS_SCOUTING, self::STATUS_EXECUTING => 'blue',
            self::STATUS_MISSION_REVIEW, self::STATUS_INBOX_REVIEW => 'yellow',
            self::STATUS_COMPLETED => 'green',
            default => 'gray',
        };
    }
}
