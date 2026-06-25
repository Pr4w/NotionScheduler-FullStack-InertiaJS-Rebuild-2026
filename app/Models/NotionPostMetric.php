<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotionPostMetric extends Model
{
    protected $fillable = [
        'content_id', 'platform', 'recorded_at',
        'views', 'likes', 'comments', 'shares', 'saves',
        'raw_payload',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(NotionPosts::class, 'content_id');
    }
}
