<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'brand_name',
        'description',
        'value_proposition',
        'pain_points_summary',
        'status',
        'is_analyzed',
        'analyzed_at',
    ];

    protected $casts = [
        'is_analyzed' => 'boolean',
        'analyzed_at' => 'datetime',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(ProductDocument::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function chatConversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class);
    }

    public function messageTemplates(): HasMany
    {
        return $this->hasMany(ProductMessageTemplate::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
