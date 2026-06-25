<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotionPostLatestMetric extends Model
{
    protected $fillable = [
        'content_id', 'platform', 'recorded_at',
        'views', 'likes', 'comments', 'shares', 'saves',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function content(): BelongsTo
    {
        return $this->belongsTo(NotionPosts::class, 'content_id');
    }
}
