<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    protected $fillable = [
        'campaign_id',
        'title',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}
