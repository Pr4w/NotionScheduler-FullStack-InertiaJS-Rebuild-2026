<?php

/*
|--------------------------------------------------------------------------
| Use-Case Pages
|--------------------------------------------------------------------------
|
| One entry per audience => /for/{slug}. Each page must make a MATERIALLY
| different argument with its own workflow — same anti-thin-content
| discipline as config/solutions.php. The footer reads this file, so
| adding an entry auto-links it.
|
*/

return [

    'creators' => [
        'name'        => 'Creators',
        'slug'        => 'creators',
        'seo_title'   => 'NotionScheduler for creators — batch and schedule content from Notion',
        'seo_desc'    => 'Film and write in batches, then let your Notion calendar drip it out. The sustainable way for solo creators to stay consistent without burning out.',
        'eyebrow'     => '🎬 For creators',
        'headline'    => 'Consistency without',
        'headline_em' => 'living in the apps.',
        'subhead'     => 'The creators who last are not the ones posting every day on impulse. They batch, they schedule, and they get their evenings back. Notion is already where you dump ideas, so that is where the calendar should live.',
        'argument'    => "The thing that kills solo creators is not lack of ideas, it is the daily context-switch. Open the app, get pulled into the feed, lose an hour, post something rushed. Batching breaks that loop: write or film several pieces in one focused session when you are in the zone, line them up in a Notion database, and let them publish on their own. Your worst enemy is the algorithm-shaped urge to be in the app constantly, and a real calendar is the cure.",
        'workflow'    => [
            ['Brain-dump in Notion', 'Ideas, hooks and rough drafts go where they already go — your Notion workspace. No new "content tool" to maintain.'],
            ['Batch a week (or month)', 'Block one session. Write the captions, drop the exports into the rows, set the dates.'],
            ['Walk away', 'NotionScheduler publishes on schedule across every platform. You check analytics when you want to, not because you have to.'],
        ],
        'objection'   => ['"But batched content feels less authentic"', 'Authenticity is about what you say, not whether you typed it sixty seconds before posting. Batching gives you the space to actually edit — which usually makes the work better, not worse.'],
        'platforms'   => ['instagram', 'tiktok', 'youtube', 'x'],
        'pull' => 'Batch when you\'re inspired. Publish when it matters. Get your evenings back.',

    ],

    'agencies' => [
        'name'        => 'Agencies',
        'slug'        => 'agencies',
        'seo_title'   => 'NotionScheduler for agencies — client content calendars in Notion',
        'seo_desc'    => 'Run every client\'s content calendar in Notion, get approvals where the work already lives, and schedule across all their accounts without per-seat tool sprawl.',
        'eyebrow'     => '🏢 For agencies',
        'headline'    => 'Every client calendar,',
        'headline_em' => 'one workspace.',
        'subhead'     => 'You already run client work in Notion. Approvals, briefs, timelines — all there. Scheduling shouldn\'t mean exporting every client into a separate tool with its own seat costs and its own login your client will never use.',
        'argument'    => "Agency scheduling tools punish you twice: per-seat pricing that scales with your client list, and an approval flow that lives somewhere your client doesn't. So you end up screenshotting drafts into email anyway. If the content calendar lives in the Notion workspace you already share with the client, approval is just a status change on a row they can already see — no new tool, no new login, no seat you're paying for so a client can log in twice a year.",
        'workflow'    => [
            ['One database per client', 'Each client gets a Notion database in the workspace you already share with them.'],
            ['Approval is a status field', 'Client reviews drafts in context and flips a status. No screenshots into email, no separate approval app.'],
            ['Schedule across their accounts', 'Connect each client\'s social accounts once. Posts publish from their Notion calendar on the agreed dates.'],
        ],
        'objection'   => ['"Our clients aren\'t in Notion"', 'They don\'t need to be Notion users — a shared page with a calendar view is about as hard to use as a Google Doc. And most agencies find it replaces three tools the client also wasn\'t really using.'],
        'platforms'   => ['instagram', 'facebook', 'linkedin', 'tiktok'],
        'pull' => 'Stop paying per seat so a client can log in twice a year.',

    ],

    'teams' => [
        'name'        => 'Teams',
        'slug'        => 'teams',
        'seo_title'   => 'NotionScheduler for teams — collaborate on social content in Notion',
        'seo_desc'    => 'Your team already lives in Notion. Plan, draft and schedule social content together without adding seats, logins or a tool half the team will ignore.',
        'eyebrow'     => '👥 For teams',
        'headline'    => 'Collaborate where',
        'headline_em' => 'your team already is.',
        'subhead'     => 'Marketing tools assume you\'ll get the whole team to adopt a new platform. You won\'t — half of them will keep working in Notion regardless. So meet them there instead of fighting it.',
        'argument'    => "Every social tool sells \"collaboration\" and then asks you to onboard your whole team into a new app with its own permissions, its own seats, and its own learning curve. Adoption stalls, and the people who matter keep planning in Notion anyway. The honest move is to accept that Notion already won the collaboration question internally. Drafting, feedback and sign-off happen there with the access people already have. Scheduling becomes the last quiet step, not a reason to migrate everyone.",
        'workflow'    => [
            ['Draft together in Notion', 'Writers, designers and managers work in the same database with the access they already have. Comments, mentions, all of it.'],
            ['Review in context', 'Feedback happens on the row, next to the asset and the caption — not in a Slack thread that loses the thread.'],
            ['Schedule once approved', 'When it\'s signed off, it\'s already where it needs to be. Set the date; it ships.'],
        ],
        'objection'   => ['"We already have a social tool"', 'Then you already know how many of your team actually log into it. This isn\'t about features — it\'s about the content plan living where the work actually happens.'],
        'platforms'   => ['linkedin', 'instagram', 'facebook', 'x'],
        'pull' => 'Notion already won the collaboration question. Schedule where the work is.',

    ],

    'notion-users' => [
        'name'        => 'Notion power users',
        'slug'        => 'notion-users',
        'seo_title'   => 'NotionScheduler for Notion power users — schedule without leaving your system',
        'seo_desc'    => 'You\'ve built a system in Notion. NotionScheduler plugs into it instead of replacing it — no forced template, no rebuild, just scheduling on top of what you already run.',
        'eyebrow'     => '⚙️ For Notion power users',
        'headline'    => 'Plugs into your system.',
        'headline_em' => "Doesn't replace it.",
        'subhead'     => 'You didn\'t spend two years building your Notion setup to bolt on a tool that demands its own rigid template. NotionScheduler adapts to the database you already have, relations, rollups, views and all.',
        'argument'    => "If you run your life or business in Notion, the fastest way to lose you is to hand you a \"connect our template\" onboarding. You don't want another system; you want scheduling to be one more capability of the system you already obsess over. The point is that it reads the database you point it at — your property names, your structure — rather than imposing a schema. Whatever views, relations and rollups you've built keep working; publishing just becomes another thing your existing setup can now do.",
        'workflow'    => [
            ['Point it at your existing database', 'No template to duplicate. Map your own properties — title, date, status, media — to what publishing needs.'],
            ['Keep your structure', 'Relations, rollups, filtered views, linked databases — untouched. Scheduling sits on top, it doesn\'t take over.'],
            ['Automate around it', 'Because it\'s just your Notion database, your existing automations and habits keep working. One more output, zero rebuild.'],
        ],
        'objection'   => ['"Will it force a specific property setup?"', 'You tell it which of your properties mean what. If you can build a rollup, the mapping step will bore you with how simple it is.'],
        'platforms'   => ['x', 'linkedin', 'instagram', 'threads'],
        'pull' => 'No template to "connect". It reads the system you already built.',
    ],

];