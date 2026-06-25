<?php

namespace App\Filament\Resources\BlogPosts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use RalphJSmit\Filament\SEO\SEO;

class BlogPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Content')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                        TextInput::make('slug')->required()->unique(ignoreRecord: true),
                        Textarea::make('excerpt')
                            ->required()->maxLength(320)->rows(3)
                            ->helperText('Used for cards, meta description, and Article schema.'),
                        SpatieMediaLibraryFileUpload::make('cover')
                            ->collection('cover')->image()->imageEditor(),
                        RichEditor::make('body')
                            ->required()
                            ->extraInputAttributes(['style' => 'min-height: 20rem; max-height: 50vh; overflow-y: auto;'])
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Internal linking')
                    ->schema([
                        Select::make('platforms')
                            ->multiple()
                            ->options(collect(config('solutions'))
                                ->mapWithKeys(fn ($s) => [$s['slug'] => $s['name']]))
                            ->helperText('Solution pages to cross-link from this post.'),
                        Select::make('use_cases')
                            ->multiple()
                            ->options(collect(config('use-cases'))
                                ->mapWithKeys(fn ($c) => [$c['slug'] => $c['name']])),
                    ])->columns(2),

                Section::make('Publishing')
                    ->schema([
                        DateTimePicker::make('published_at')
                            ->helperText('Empty = draft. Future = scheduled. Past = live.'),
                    ]),

                Section::make('SEO')
                    ->schema([
                        SEO::make(),
                    ]),

            ])->columns(1);
    }
}
