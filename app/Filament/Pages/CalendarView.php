<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\MyCalendarWidget;
use Filament\Pages\Page;

class CalendarView extends Page
{
    protected string $view = 'filament.pages.calendar-view';

    protected function getHeaderWidgets(): array
    {
        return [
            MyCalendarWidget::class,
        ];
    }
}
