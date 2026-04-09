<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectMessage extends Model
{
    protected $fillable = [
        'prospect_id',
        'channel',
        'subject',
        'content',
        'original_ai_content',
        'status',
        'sent_at',
        'delivery_metadata',
    ];

    protected $casts = [
        'delivery_metadata' => 'array',
        'sent_at' => 'datetime',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_APPROVED = 'approved';
    const STATUS_SENT = 'sent';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_READ = 'read';
    const STATUS_FAILED = 'failed';

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function getChannelIconAttribute(): string
    {
        return match ($this->channel) {
            'whatsapp' => '💬',
            'email' => '📧',
            'instagram' => '📸',
            default => '📤',
        };
    }
}
