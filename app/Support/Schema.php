<?php

namespace App\Support;

use RalphJSmit\Laravel\SEO\Support\SEOData;

class Schema
{
    /**
     * Build a SchemaCollection ->add() closure for a FAQPage from an
     * array of [question, answer] pairs (the shape used in
     * config/solutions.php). Returns null if there are no questions,
     * so callers can conditionally attach it.
     */
    public static function faqClosure(array $faqs): ?\Closure
    {
        if (empty($faqs)) {
            return null;
        }

        return function (SEOData $SEOData) use ($faqs) {
            return [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn ($pair) => [
                    '@type' => 'Question',
                    'name' => $pair[0],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $pair[1],
                    ],
                ], array_values($faqs)),
            ];
        };
    }
}
