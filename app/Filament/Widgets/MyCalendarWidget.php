<?php

namespace App\Filament\Widgets;

use App\Models\NotionPosts;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MyCalendarWidget extends CalendarWidget
{
    protected function getEvents(FetchInfo $info): Collection|array|Builder
    {

        // You probably want to query only visible events:
        return NotionPosts::query()
            ->whereDate('scheduled_date', '>=', $info->start)
            ->whereDate('scheduled_date', '<=', $info->end);

    }
}
