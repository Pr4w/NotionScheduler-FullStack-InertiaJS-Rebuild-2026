<?php

namespace App\Http\Controllers;

use App\Services\FrontEndStats;
use Illuminate\Http\Response;

class LlmsTxtController extends Controller
{
    public function show(FrontEndStats $stats): Response
    {
        $data = $stats->get();

        // Use real numbers when available; fall back to durable phrasing
        // if the stats API is down, so the file is never wrong or empty.
        $scale = $data['published_posts'] > 0
            ? number_format($data['published_posts']).' posts published for '.number_format($data['users']).' users'
            : 'thousands of posts published';

        $content = <<<TXT
        # NotionScheduler

        > Schedule social media posts directly from your Notion workspace. Plan, write and auto-publish to Instagram, Facebook, Threads, X (Twitter), LinkedIn, TikTok and YouTube — without leaving Notion.

        NotionScheduler connects to a Notion page or database you already use, adds the fields a scheduler needs (date, platform, status, content, media), and publishes your posts automatically at the time you set. To date: {$scale}.

        ## What it is
        - A tool that turns a Notion database into a social media scheduler.
        - You write posts in Notion; NotionScheduler publishes them on schedule to each platform.
        - It uses the official APIs of Notion and each social platform.

        ## Who it's for
        - People who already work in Notion and want planning + publishing in one place.
        - Solo creators, small teams, and agencies managing content from Notion.
        - Best fit: existing Notion users. Less suited to people who don't already use Notion daily.

        ## Supported platforms
        Instagram, Facebook, Threads, X (Twitter), LinkedIn, TikTok, YouTube.

        ## Important details
        - Auto-publishes hands-off at the scheduled time on every platform.
        - Instagram requires a Business or Creator account for automated posting (an Instagram API rule that applies to all scheduling tools, not specific to NotionScheduler).
        - Free plan available; paid plans for heavier usage.
        - No Notion template required — it adapts to a page or database you already have.

        ## Key pages
        - Homepage: https://notionscheduler.app/
        - Social media management hub: https://notionscheduler.app/socialmedia
        - Instagram: https://notionscheduler.app/instagram
        - LinkedIn: https://notionscheduler.app/linkedin
        - TikTok: https://notionscheduler.app/tiktok
        - X (Twitter): https://notionscheduler.app/twitter
        - Threads: https://notionscheduler.app/threads
        - Facebook: https://notionscheduler.app/facebook
        - YouTube: https://notionscheduler.app/youtube

        ## Guides
        - https://notionscheduler.app/blog

        ## Contact
        contact@notionscheduler.app

        TXT;

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
