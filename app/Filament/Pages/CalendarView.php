<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\MyCalendarWidget;
use Filament\Pages\Page;

use BackedEnum;

class CalendarView extends Page
{
    protected string $view = 'filament.pages.calendar-view';
    protected static string|BackedEnum|null $navigationIcon = "heroicon-o-calendar";

    protected function getHeaderWidgets(): array
    {
        return [
            MyCalendarWidget::class,
        ];
    }
}
