<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'chat_conversation_id',
        'role',
        'content',
        'context_files_used',
        'input_tokens',
        'output_tokens',
        'cost_usd',
    ];

    protected $casts = [
        'context_files_used' => 'array',
        'cost_usd' => 'decimal:4',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }
}
