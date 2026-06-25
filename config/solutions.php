<?php

/*
|--------------------------------------------------------------------------
| Solution Pages
|--------------------------------------------------------------------------
|
| One entry per platform => /{slug}. Each entry carries genuinely unique
| copy (hero, intro, benefits, platform-specific FAQ) so Google sees these
| as distinct pages, not boilerplate duplicates. Keep the prose specific to
| each platform — that's the entire SEO point of these pages.
|
| Add a platform: add a key here + add the slug to the route constraint in
| routes/web.php. The footer reads this same file, so it auto-links.
|
*/

return [

    'instagram' => [
        'name'        => 'Instagram',
        'slug'        => 'instagram',
        'accent'      => '#E1306C',
        'seo_title'   => 'Schedule Instagram posts from Notion',
        'seo_desc'    => 'Plan your Instagram grid, write captions and schedule Reels and posts straight from your Notion workspace. Free to start, no plugin to install.',
        'eyebrow'     => '📸 Instagram + Notion',
        'headline'    => 'Plan your Instagram',
        'headline_em' => 'in the doc you already write in.',
        'subhead'     => 'Draft captions, line up your grid and queue Reels without ever opening another scheduler. It all happens in the Notion database you already use to plan content.',
        'intro_title' => 'Your Instagram calendar belongs next to your ideas',
        'intro_body'  => "Most Instagram tools make you re-type captions you already wrote somewhere else. If you brainstorm posts in Notion — and most people do — exporting them into a separate planner just adds a step where things get lost. NotionScheduler reads the row you already filled in and posts it for you.",
        'benefits'    => [
            ['Grid planning', 'See your upcoming feed in Notion\'s gallery view before anything goes live.'],
            ['Reels & carousels', 'Schedule single images, carousels and Reels from the same Notion row.'],
            ['Caption + first comment', 'Write the caption and the hashtag comment together, where you draft everything else.'],
            ['No business-account headaches', 'Connect once via the official API. We handle the token refresh nonsense.'],
        ],
        'faq'         => [
            ['Can I schedule Instagram Reels?', 'Yes. Drop a video into the Notion row, set the date, and it publishes as a Reel. Images and carousels work the same way.'],
            ['Do I need an Instagram Business or Creator account?', 'Yes — Instagram\'s official API requires a Business or Creator account connected to a Facebook Page. That\'s an Instagram requirement, not ours, and it takes two minutes to switch.'],
            ['Can I schedule the first comment with hashtags?', 'Yes. Keep your caption clean and put your hashtags in a separate field — we post them as the first comment automatically.'],
        ],
    ],

    'facebook' => [
        'name'        => 'Facebook',
        'slug'        => 'facebook',
        'accent'      => '#1877F2',
        'seo_title'   => 'Schedule Facebook posts from Notion',
        'seo_desc'    => 'Queue Facebook Page posts, links and images directly from Notion. Plan a month of content in one database and let NotionScheduler publish it.',
        'eyebrow'     => '👍 Facebook + Notion',
        'headline'    => 'Run your Facebook Page',
        'headline_em' => 'without leaving Notion.',
        'subhead'     => 'Write the post, attach the image or link, pick a date. NotionScheduler publishes to your Facebook Page on schedule while you stay in your workspace.',
        'intro_title' => 'A content calendar your whole team can already see',
        'intro_body'  => "Facebook Pages are usually run by more than one person, and everyone on your team already has Notion access. Instead of inviting them into yet another tool and managing seats, you keep the plan where it lives and we handle the publishing in the background.",
        'benefits'    => [
            ['Page posts & links', 'Text, image and link posts to any Facebook Page you manage.'],
            ['Shared by default', 'Your team already has Notion access — no new logins to hand out.'],
            ['Month-at-a-glance', 'Notion\'s calendar view shows the whole content plan in one place.'],
            ['Official Pages API', 'No scraping, no risky workarounds — the proper Meta integration.'],
        ],
        'faq'         => [
            ['Can I post to multiple Facebook Pages?', 'Yes. Connect each Page once, then choose which Page a given Notion row publishes to.'],
            ['Does it support link previews?', 'Yes. Paste a URL and Facebook generates its standard link preview when the post goes out.'],
            ['Can I schedule to a personal profile?', 'No — Meta\'s API only allows posting to Pages, not personal profiles. This applies to every scheduling tool, not just us.'],
        ],
    ],

    'threads' => [
        'name'        => 'Threads',
        'slug'        => 'threads',
        'accent'      => '#000000',
        'seo_title'   => 'Schedule Threads posts from Notion',
        'seo_desc'    => 'Write and schedule Threads posts from your Notion workspace. Plan your text-first content alongside everything else you publish.',
        'eyebrow'     => '🧵 Threads + Notion',
        'headline'    => 'Queue your Threads',
        'headline_em' => 'where you already think out loud.',
        'subhead'     => 'Threads rewards consistency. Draft a week of posts in Notion in one sitting and let them go out on a steady schedule instead of all at once.',
        'intro_title' => 'Consistency without living in the app',
        'intro_body'  => "Threads is text-first and momentum-driven, which means the hard part is showing up regularly, not the posting itself. Batching a week of drafts in a Notion database and scheduling them is far more sustainable than opening the app every day and hoping for inspiration.",
        'benefits'    => [
            ['Batch a week in one sitting', 'Write several posts at once in Notion, schedule them across the week.'],
            ['Text-first, distraction-free', 'No feed to fall into while you\'re trying to write.'],
            ['Pair with Instagram', 'Plan Threads and Instagram side by side in the same database.'],
            ['Official API', 'Built on Meta\'s official Threads API.'],
        ],
        'faq'         => [
            ['Can I schedule a thread (multiple connected posts)?', 'You can schedule individual Threads posts. Multi-post chains depend on current API support — check the app for the latest, as Meta is still expanding the Threads API.'],
            ['Can I cross-post the same content to Instagram?', 'You can duplicate a Notion row and target Instagram instead — quick to do, and it keeps the copy slightly different per platform, which is healthier for reach.'],
            ['Do I need a special account type?', 'A standard Threads account linked to your Instagram is enough.'],
        ],
    ],

    'twitter' => [
        'name'        => 'Twitter',
        'slug'        => 'twitter',
        'accent'      => '#1DA1F2',
        'seo_title'   => 'Schedule Twitter posts from Notion',
        'seo_desc'    => 'Schedule tweets from Notion. Draft, queue and time your Twitter content in the same workspace you plan everything else.',
        'eyebrow'     => '🐦 Twitter + Notion',
        'headline'    => 'Schedule your tweets',
        'headline_em' => 'from your Notion table.',
        'subhead'     => 'Write tweets in Notion, set the time, done. Good for people who want to plan ahead instead of doom-scrolling for an hour every morning.',
        'intro_title' => 'Plan tweets like the content they are',
        'intro_body'  => "Tweets are content too, and content that gets planned tends to be better than content typed into the box at 2am. Drafting in Notion lets you sit with a tweet, edit it, line it up with the rest of your week, and schedule it without the app pulling you into a two-hour rabbit hole.",
        'benefits'    => [
            ['Draft then schedule', 'Write tweets in Notion, queue them for the right time.'],
            ['Media supported', 'Attach images or video in the Notion row.'],
            ['Plan a whole week', 'See every queued tweet in one calendar.'],
            ['Also covers X', 'Same account, same setup — see the X page if you prefer that branding.'],
        ],
        'faq'         => [
            ['Is this different from your X page?', 'Same product, same connection — Twitter and X are the same account. We keep both pages because people still search both terms.'],
            ['Can I schedule threads?', 'You can schedule individual tweets reliably. Thread support depends on current API access — the app shows what\'s available.'],
            ['Does it cost anything for API access?', 'You connect your own account through the official API; you don\'t need a paid X API tier for standard scheduled posting through us.'],
        ],
    ],

    'x' => [
        'name'        => 'X',
        'slug'        => 'x',
        'accent'      => '#000000',
        'seo_title'   => 'Schedule X (Twitter) posts from Notion',
        'seo_desc'    => 'Schedule posts to X directly from Notion. Plan your X content in the workspace you already use and let NotionScheduler publish on time.',
        'eyebrow'     => '𝕏 X + Notion',
        'headline'    => 'Post to X on schedule,',
        'headline_em' => 'planned entirely in Notion.',
        'subhead'     => 'Whether you call it X or Twitter, the workflow is the same: draft in Notion, set a time, let it publish. No tab-switching, no forgetting.',
        'intro_title' => 'X content, planned like everything else you do',
        'intro_body'  => "Posting to X consistently is mostly a planning problem, not a posting problem. If your ideas, drafts and campaign notes already live in Notion, the natural place to schedule X posts is right there next to them — not in a separate dashboard you have to remember to check.",
        'benefits'    => [
            ['Draft in Notion', 'Write and edit posts where the rest of your plan lives.'],
            ['Media + links', 'Images, video and links all post correctly.'],
            ['One content calendar', 'X sits alongside LinkedIn, Instagram and the rest.'],
            ['Official X API', 'Connected through the official integration.'],
        ],
        'faq'         => [
            ['X or Twitter — which page should I use?', 'It doesn\'t matter; it\'s the same connection. Both pages exist because people search both names.'],
            ['Can I schedule images and video?', 'Yes. Attach media in the Notion row and it posts with the content.'],
            ['Do I need X Premium?', 'No. Standard scheduled posting through NotionScheduler doesn\'t require a paid X tier.'],
        ],
    ],

    'linkedin' => [
        'name'        => 'LinkedIn',
        'slug'        => 'linkedin',
        'accent'      => '#0A66C2',
        'seo_title'   => 'Schedule LinkedIn posts from Notion',
        'seo_desc'    => 'Schedule LinkedIn posts and company-page updates from Notion. Plan thought-leadership content in the workspace where you draft it.',
        'eyebrow'     => '💼 LinkedIn + Notion',
        'headline'    => 'Schedule LinkedIn posts',
        'headline_em' => 'from where you actually write them.',
        'subhead'     => 'LinkedIn posts that perform are usually drafted, edited and slept on — not typed live. Do all of that in Notion, then schedule it.',
        'intro_title' => 'Thought leadership starts as a draft, not a live post',
        'intro_body'  => "The LinkedIn posts that do well are almost never first drafts. They get written, trimmed, and revisited. Notion is already where that kind of writing happens for most people, so scheduling straight from it means your best thinking doesn't get flattened by LinkedIn's tiny editor.",
        'benefits'    => [
            ['Personal & company pages', 'Schedule to your profile or to company pages you manage.'],
            ['Write, then sit on it', 'Draft in Notion, refine, schedule when it\'s actually good.'],
            ['Plan a content cadence', 'Map out a posting rhythm in the calendar view.'],
            ['Official LinkedIn API', 'Proper integration, no browser automation.'],
        ],
        'faq'         => [
            ['Can I post to a LinkedIn company page?', 'Yes, for pages where you have an admin role. Personal profile posting is supported too.'],
            ['Do documents/PDF carousels work?', 'Standard text and image/video posts are fully supported. Document posts depend on current API capability — the app reflects what\'s live.'],
            ['Can my team collaborate on drafts?', 'Yes — the drafting happens in your Notion workspace, so anyone with access can contribute before it\'s scheduled.'],
        ],
    ],

    'tiktok' => [
        'name'        => 'TikTok',
        'slug'        => 'tiktok',
        'accent'      => '#000000',
        'seo_title'   => 'Schedule TikTok posts from Notion',
        'seo_desc'    => 'Schedule TikTok videos from your Notion workspace. Plan your content calendar and queue uploads without juggling another app.',
        'eyebrow'     => '🎵 TikTok + Notion',
        'headline'    => 'Plan your TikToks',
        'headline_em' => 'in the calendar you already trust.',
        'subhead'     => 'Upload the video to the Notion row, write the caption, set the date. We push it to TikTok on schedule so you can batch-film and forget.',
        'intro_title' => 'Batch-film, then let the calendar do the rest',
        'intro_body'  => "The sustainable way to run TikTok is to film several videos in one session and release them over time, not scramble for a daily post. That only works if you have a real calendar — and Notion's is one you already check. Attach the export, schedule it, move on.",
        'benefits'    => [
            ['Video uploads', 'Drop your finished export into the Notion row.'],
            ['Batch & space out', 'Film a batch, schedule them across two weeks.'],
            ['Caption planning', 'Write hooks and captions where you plan everything.'],
            ['Official Content Posting API', 'Built on TikTok\'s official API.'],
        ],
        'faq'         => [
            ['Does it auto-publish or save as a draft?', 'Depends on TikTok\'s API permissions for your account — some accounts get direct publish, others get a notification to confirm in-app. The app shows your account\'s status.'],
            ['What video formats work?', 'Standard MP4 exports from any editor. Keep within TikTok\'s size/length limits.'],
            ['Can I schedule the caption and sounds?', 'Captions yes. Sound selection has to happen in the TikTok app due to licensing — that\'s a platform limitation.'],
        ],
    ],

    'youtube' => [
        'name'        => 'YouTube',
        'slug'        => 'youtube',
        'accent'      => '#FF0000',
        'seo_title'   => 'Schedule YouTube uploads from Notion',
        'seo_desc'    => 'Plan and schedule YouTube videos and Shorts from Notion. Manage your upload calendar alongside the rest of your content.',
        'eyebrow'     => '▶️ YouTube + Notion',
        'headline'    => 'Schedule YouTube uploads',
        'headline_em' => 'from your production tracker.',
        'subhead'     => 'You already track video production in Notion. Attach the final file, fill in the title and description, and schedule the upload from the same place.',
        'intro_title' => 'Your production pipeline already lives here',
        'intro_body'  => "Video teams almost always track scripting, filming and editing in Notion. The upload is the last step of that pipeline — so it makes far more sense to schedule it from the same board than to switch into YouTube Studio and re-enter metadata you already wrote in your tracker.",
        'benefits'    => [
            ['Videos & Shorts', 'Schedule long-form uploads and Shorts from one row.'],
            ['Title, description, tags', 'Fill in metadata in Notion where you drafted it.'],
            ['Pipeline-friendly', 'The upload becomes the final stage of your existing board.'],
            ['Official YouTube Data API', 'Proper integration with your channel.'],
        ],
        'faq'         => [
            ['Can I set the video to publish at a specific time?', 'Yes. Set the date and time in Notion and the upload publishes (or is set to scheduled) accordingly.'],
            ['Does it support Shorts?', 'Yes — vertical videos under the Shorts length are handled as Shorts by YouTube automatically.'],
            ['Can I schedule the thumbnail too?', 'Custom thumbnail support depends on your channel\'s API permissions; the app indicates what\'s available for your account.'],
        ],
    ],

];