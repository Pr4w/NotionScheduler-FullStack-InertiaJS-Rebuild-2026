<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\NotionAccessTokens;
use Notion\Notion;
use App\Models\NotionScaffolding;
use Illuminate\Support\Facades\Log;

class setDefaultScaffolding extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:set-default-scaffolding';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        // Get MHH's token
        $token = NotionAccessTokens::where('userid', 1)->first();

        // Get the page where everything is located
        $pageId = "b5e01560c65f41b796f7e1d635370b71";
        $notion = Notion::create($token->token);
        $content = $notion->blocks()->findChildrenRecursive($pageId);

        // Create the content array
        $new_content = [];
        foreach ($content as $con) {
            $new_content[] = $con->toArray();
        }

        // Upload it to the DB
        $new = new NotionScaffolding;
        $new->scaffolding = json_encode($new_content);
        $new->save();

        return;
    }
}
