<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Pages;

use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareException;
use notwonderful\FilamentCloudflare\Facades\Cloudflare;

class CloudflareCache extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = 'Cache Management';
    protected static \UnitEnum|string|null $navigationGroup = 'Cloudflare';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament-cloudflare::pages.cloudflare-cache';

    public function getTitle(): string
    {
        return 'Cache Management';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Purge Cache')
                    ->description('Clear cached content from Cloudflare')
                    ->schema([
                        Forms\Components\Toggle::make('purge_everything')
                            ->label('Purge Everything')
                            ->default(true)
                            ->helperText('Clear all cached content'),

                        Forms\Components\Textarea::make('files')
                            ->label('Specific Files')
                            ->rows(3)
                            ->helperText('Enter URLs to purge (one per line)')
                            ->visible(fn (Get $get) => !$get('purge_everything')),

                        Forms\Components\Textarea::make('tags')
                            ->label('Cache Tags')
                            ->rows(3)
                            ->helperText('Enter cache tags to purge (one per line)')
                            ->visible(fn (Get $get) => !$get('purge_everything')),

                        Forms\Components\Textarea::make('hosts')
                            ->label('Hosts')
                            ->rows(3)
                            ->helperText('Enter hosts to purge (one per line)')
                            ->visible(fn (Get $get) => !$get('purge_everything')),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('purge_cache')
                ->label('Purge Cache')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->schema([
                    Forms\Components\Toggle::make('purge_everything')
                        ->label('Purge Everything')
                        ->default(true)
                        ->helperText('Clear all cached content')
                        ->live(),

                    Forms\Components\Textarea::make('files')
                        ->label('Specific Files (URLs)')
                        ->rows(5)
                        ->helperText('Enter URLs to purge, one per line')
                        ->placeholder('https://example.com/page1.html' . PHP_EOL . 'https://example.com/page2.html')
                        ->visible(fn (Get $get) => !$get('purge_everything')),

                    Forms\Components\Textarea::make('tags')
                        ->label('Cache Tags')
                        ->rows(5)
                        ->helperText('Enter cache tags to purge, one per line')
                        ->visible(fn (Get $get) => !$get('purge_everything')),

                    Forms\Components\Textarea::make('hosts')
                        ->label('Hosts')
                        ->rows(5)
                        ->helperText('Enter hosts to purge, one per line')
                        ->visible(fn (Get $get) => !$get('purge_everything')),
                ])
                ->action(function (array $data) {
                    try {
                        $purgeEverything = $data['purge_everything'] ?? true;
                        $files = $this->parseTextarea($data['files'] ?? null);
                        $tags = $this->parseTextarea($data['tags'] ?? null);
                        $hosts = $this->parseTextarea($data['hosts'] ?? null);

                        Cloudflare::cache()->purgeCache($purgeEverything, $files, $tags, $hosts);

                        Notification::make()
                            ->title('Cache purged successfully')
                            ->success()
                            ->send();
                    } catch (CloudflareException $e) {
                        Notification::make()
                            ->title('Error: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Purge Cache')
                ->modalDescription('Are you sure you want to purge the cache? This action cannot be undone.')
                ->modalSubmitActionLabel('Purge Cache'),
        ];
    }

    /** @return array<int, string>|null */
    protected function parseTextarea(?string $value): ?array
    {
        return match (true) {
            empty($value) => null,
            default => array_filter(array_map('trim', explode("\n", $value))),
        };
    }
}
