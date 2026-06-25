<?php

namespace App\Http\Controllers;

use App\Support\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Config;
use RalphJSmit\Laravel\SEO\SchemaCollection;
use RalphJSmit\Laravel\SEO\Support\SEOData;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SolutionController extends Controller
{
    public function show(string $platform): View
    {
        $solution = Config::get("solutions.{$platform}");

        if (! $solution) {
            throw new NotFoundHttpException;
        }

        $schema = SchemaCollection::make();

        if ($faqClosure = Schema::faqClosure($solution['faq'] ?? [])) {
            $schema->add($faqClosure);
        }

        // SoftwareApplication — per-platform, so the featureList is specific
        // to this platform rather than generic.
        $schema->add(fn (SEOData $SEOData) => [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => 'NotionScheduler',
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'description' => $solution['seo_desc'],
            'url' => url('/'.$solution['slug']),
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'EUR',
                'description' => 'Free plan available; paid plans for heavier usage.',
            ],
            'featureList' => [
                'Schedule '.$solution['name'].' posts from a Notion database',
                'Plan content in Notion\'s calendar view',
                'Auto-publish to '.$solution['name'].' on schedule',
            ],
        ]);

        // BreadcrumbList — Home > {Platform}
        $schema->add(fn (SEOData $SEOData) => [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Home',
                    'item' => url('/'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Schedule '.$solution['name'].' from Notion',
                    'item' => url('/'.$solution['slug']),
                ],
            ],
        ]);

        return view('pages.solution', [
            'solution' => $solution,
            'SEOData' => new SEOData(
                title: $solution['seo_title'],
                description: $solution['seo_desc'],
                schema: $schema,
            ),
        ]);
    }
}
