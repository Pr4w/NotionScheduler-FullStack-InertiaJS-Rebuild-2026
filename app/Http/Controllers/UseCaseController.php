<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Config;
use RalphJSmit\Laravel\SEO\Support\SEOData;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UseCaseController extends Controller
{
    public function show(string $useCase): View
    {
        $case = Config::get("use-cases.{$useCase}");

        if (! $case) {
            throw new NotFoundHttpException;
        }

        return view('pages.use-case', [
            'case' => $case,
            'SEOData' => new SEOData(
                title: $case['seo_title'],
                description: $case['seo_desc'],
            ),
        ]);
    }
}
