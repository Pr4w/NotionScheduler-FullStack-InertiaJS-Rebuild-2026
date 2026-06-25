<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Posting engine master switch
    |--------------------------------------------------------------------------
    |
    | When false, the engine will NOT publish to real social accounts or write
    | back to real Notion databases. This is the preprod safety guard: a
    | subdomain pointed at the live database can run trials without firing real
    | side-effects. Defaults to false so a misconfigured environment fails
    | safe (loudly not-posting) rather than dangerously posting.
    |
    */

    'enabled' => (bool) env('POSTING_ENABLED', false),

];
