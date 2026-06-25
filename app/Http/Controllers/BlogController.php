<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Contracts\View\View;
use RalphJSmit\Laravel\SEO\Support\SEOData;

class BlogController extends Controller
{
    public function index(): View
    {
        return view('pages.blog-index', [
            'posts' => BlogPost::published()->latest('published_at')->paginate(12),
            'SEOData' => new SEOData(
                title: 'Blog',
                description: 'Guides on planning, batching and scheduling social content with Notion.',
            ),
        ]);
    }

    public function show(BlogPost $blogPost): View
    {
        abort_if(
            is_null($blogPost->published_at) || $blogPost->published_at->isFuture(),
            404
        );

        return view('pages.blog-show', ['post' => $blogPost]);
    }
}
