<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Log;

use App\Models\NotionSocialAccounts;
use App\Models\NotionSocialAccountsAccessTokens;
use App\Models\NotionDatabases;

class removeUnusedSocialTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:remove-unused-social-tokens';

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

        $social_accounts = NotionSocialAccounts::get()->pluck('token_id')->all();
        $unusedTokens = NotionSocialAccountsAccessTokens::whereNotIn('id', $social_accounts)->delete();
        $unusued_databases = NotionDatabases::where('is_valid', 0)->where('is_active', 0)->delete();

        return;
    }
}
