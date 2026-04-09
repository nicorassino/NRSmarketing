<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDocument extends Model
{
    protected $fillable = [
        'product_id',
        'title',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'type',
        'extracted_text',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
