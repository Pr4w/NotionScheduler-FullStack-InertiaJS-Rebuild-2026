<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RalphJSmit\Laravel\SEO\SchemaCollection;
use RalphJSmit\Laravel\SEO\Support\HasSEO;
use RalphJSmit\Laravel\SEO\Support\SEOData;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class BlogPost extends Model implements HasMedia
{
    use HasSEO;
    use InteractsWithMedia;

    protected $fillable = [
        'title', 'slug', 'excerpt', 'body',
        'use_cases', 'platforms', 'published_at',
    ];

    protected $casts = [
        'use_cases' => 'array',
        'platforms' => 'array',
        'published_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Only published (published_at in the past) posts on the public site. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('cover') ?: null;
    }

    /**
     * Feeds the SEO package via ->for($post). Produces Article +
     * BreadcrumbList JSON-LD automatically from this data.
     */
    public function getDynamicSEOData(): SEOData
    {
        return new SEOData(
            title: $this->title,
            description: $this->excerpt,
            author: 'NotionScheduler',
            image: $this->cover_url,
            url: route('blog.show', $this->slug),
            published_time: $this->published_at,
            // dateModified must never precede datePublished (backdated/pre-publish edits invert them).
            modified_time: max($this->updated_at, $this->published_at),
            section: 'Blog',
            type: 'article',
            schema: SchemaCollection::make()
                ->addArticle()
                ->addBreadcrumbs(function ($breadcrumbs, SEOData $SEOData) {
                    return $breadcrumbs->prependBreadcrumbs([
                        'Home' => url('/'),
                        'Blog' => route('blog.index'),
                    ]);
                }),
        );
    }
}
