<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Notion\Search\Query;
use Notion\Pages\Page;

use Notion\Blocks\Heading1;
use Notion\Blocks\ToDo;
use Notion\Blocks\Callout;
use Notion\Blocks\Quote;
use Notion\Common\Emoji;
use Notion\Common\RichText;
use Notion\Pages\PageParent;
use Notion\Databases\Database;
use Notion\Databases\DatabaseParent;
use Notion\Pages\Properties\Number;
use Notion\Common\Color;

use App\Models\NotionScaffolding;
use Illuminate\Support\Facades\Log;

class NotionPages extends Model
{

    public static function removeDashesFromPageId($id) {

        return str_replace('-', '', $id);

    }

    public static function createDefaultScaffolding(
        $notion,
        $page_id,
        $page_title,
        $page_emoji
    ) {

        // Find the page for us
        $parent = PageParent::page($page_id);

        // Create
        $page = Page::create($parent)
            ->changeTitle($page_title)
            ->changeIcon(
                \Notion\Common\Icon::fromFile(
                    \Notion\Common\File::createExternal("https://notionscheduler.app/favicon.png")
                )
            );

//         $content = [
//             Callout::fromString(
//                 "💡", 
//                 "Welcome to Notion Scheduler!

// The Database below is where you'll be able to store all of your posts to schedule them across your various social media accounts.

// Head over to https://app.notionscheduler.app to manage your accounts & posts.
// "
//             )->changeColor(Color::GreenBackground)
//             ->addChild(
//                 Quote::fromString("⚠️ Please make sure to ")
//                     ->addText(
//                         RichText::fromString("never")->bold()
//                     )->addText(
//                         RichText::fromString(" modify the column names and properties within this Database, as it could break some of NotionScheduler's integrations.")
//                     ),
//                 ),
//             Callout::fromString("💡", "Keep in mind that once a post has been marked as 'ready to post' and is scheduled, any changes made to the schedule date won't be taken into account. To re-schedule a post manually, head over to your NotionScheduler Dashboard and hit 'reschedule' on your post.")->ChangeColor(Color::OrangeBackground),
//             Heading1::fromString("Scheduler")->changeColor(Color::Red),
//         ];

        $content = (array) json_decode(NotionScaffolding::latest()->first()->scaffolding, true);
        // Log::info($content);
        foreach ($content as $key => $con) {
            $content[$key] =  \Notion\Blocks\BlockFactory::fromArray($con);
        }

        $page = $notion->pages()->create($page, $content);

        return $page;

    }
    
}