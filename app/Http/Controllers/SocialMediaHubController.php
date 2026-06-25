<?php

namespace App\Http\Controllers;

use App\Support\Schema;
use Illuminate\Contracts\View\View;
use RalphJSmit\Laravel\SEO\SchemaCollection;
use RalphJSmit\Laravel\SEO\Support\SEOData;

class SocialMediaHubController extends Controller
{
    public function show(): View
    {
        $faqs = [
            ['Can Notion post to social media by itself?',
                'No. Notion is a database, it has no posting ability on its own. NotionScheduler is the layer that reads your Notion database and publishes to each platform for you, on the dates you set.'],
            ['Which platforms can I manage from Notion?',
                'Instagram, Facebook, Threads, X (Twitter), LinkedIn, TikTok and YouTube. You write the post in a Notion row, pick the account and date, and it publishes there automatically.'],
            ['Is Notion actually good for social media management?',
                'For people who already work in Notion, yes, it keeps planning and publishing in one place instead of two. If you do not already use Notion daily, a dedicated tool will feel less fiddly. We are upfront about that because it is the main reason people stay or leave.'],
            ['Do I need a template or a specific database setup?',
                'No. You connect a page or database you already have and NotionScheduler adds the fields it needs (date, platform, status, content, media). You can also start from a fresh page if you prefer.'],
            ['Does it really auto-publish, or just remind me?',
                'It genuinely auto-publishes at the scheduled time on every platform. The one rule worth knowing: Instagram only allows automated posting to Business or Creator accounts, which is an Instagram API requirement that applies to every tool, not just this one.'],
        ];

        $schema = SchemaCollection::make();

        if ($faqClosure = Schema::faqClosure($faqs)) {
            $schema->add($faqClosure);
        }

        return view('pages.social-media-hub', [
            'faqs' => $faqs,
            'SEOData' => new SEOData(
                title: 'Notion for social media management',
                description: 'Manage your whole social media presence from Notion: plan, write and auto-publish to Instagram, LinkedIn, X, TikTok and more from one database. The honest guide.',
                schema: $schema,
            ),
        ]);
    }
}
