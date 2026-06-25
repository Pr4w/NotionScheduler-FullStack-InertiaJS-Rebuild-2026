<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Filament\Resources\BlogPosts\BlogPostResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateBlogPost extends CreateRecord
{
    protected static string $resource = BlogPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importJson')
                ->label('Paste JSON to pre-fill')
                ->icon('heroicon-o-clipboard-document')
                ->color('gray')
                ->schema([
                    Textarea::make('json')
                        ->label('Post JSON')
                        ->rows(12)
                        ->helperText('Paste the JSON block. Fills everything except cover image and SEO fields, set those by hand after.')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $decoded = json_decode($data['json'], true);

                    if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                        Notification::make()
                            ->danger()
                            ->title('Invalid JSON')
                            ->body('Could not parse. Check for a stray comma or unescaped quote.')
                            ->send();

                        return;
                    }

                    // Whitelist only the fields the form actually has, so a
                    // stray key in the JSON can't inject anything unexpected.
                    $allowed = [
                        'title', 'slug', 'excerpt', 'body',
                        'platforms', 'use_cases', 'published_at',
                    ];

                    $this->form->fill(
                        array_intersect_key($decoded, array_flip($allowed))
                    );

                    Notification::make()
                        ->success()
                        ->title('Form pre-filled')
                        ->body('Review it, add the cover image and SEO fields, then create.')
                        ->send();
                }),
        ];
    }
}
