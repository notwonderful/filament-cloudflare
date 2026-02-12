<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Pages;

use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareApiException;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareRequestException;
use notwonderful\FilamentCloudflare\Facades\Cloudflare;

/**
 * @property \Filament\Schemas\Schema $form
 */
class CloudflareEdgeCaching extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = 'Edge Caching';
    protected static \UnitEnum|string|null $navigationGroup = 'Cloudflare';
    protected static ?int $navigationSort = 7;
    protected string $view = 'filament-cloudflare::pages.cloudflare-edge-caching';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?bool $guestCacheEnabled = false;
    public ?int $guestCacheSeconds = 3600;
    public ?bool $mediaCacheEnabled = false;
    public ?int $mediaCacheSeconds = 86400;
    public ?string $mediaPathPrefix = '/storage';

    public function getTitle(): string
    {
        return 'Edge Caching';
    }

    public function mount(): void
    {
        $this->loadCurrentState();

        $this->form->fill([
            'guest_cache_enabled' => $this->guestCacheEnabled,
            'guest_cache_seconds' => $this->guestCacheSeconds,
            'media_cache_enabled' => $this->mediaCacheEnabled,
            'media_cache_seconds' => $this->mediaCacheSeconds,
            'media_path_prefix' => $this->mediaPathPrefix,
        ]);
    }

    protected function loadCurrentState(): void
    {
        try {
            $this->guestCacheEnabled = Cloudflare::edgeCaching()->isGuestCacheEnabled();
        } catch (\Throwable $e) {
            $this->guestCacheEnabled = false;
        }

        try {
            $this->mediaCacheEnabled = Cloudflare::edgeCaching()->isMediaCacheEnabled();
        } catch (\Throwable $e) {
            $this->mediaCacheEnabled = false;
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->schema([
                Section::make('Guest Page Caching')
                    ->description('Cache HTML pages for guests (users without session cookies) at Cloudflare edge locations')
                    ->schema([
                        Forms\Components\Toggle::make('guest_cache_enabled')
                            ->label('Enable Guest Page Caching')
                            ->live()
                            ->helperText('Cache pages for users who are not logged in'),

                        Forms\Components\TextInput::make('guest_cache_seconds')
                            ->label('Cache Duration (seconds)')
                            ->numeric()
                            ->required()
                            ->helperText('How long to cache pages (e.g., 3600 = 1 hour, 86400 = 1 day)')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('guest_cache_enabled')),
                    ]),

                Section::make('Media Attachment Caching')
                    ->description('Cache media files (images, videos, audio) at Cloudflare edge locations')
                    ->schema([
                        Forms\Components\Toggle::make('media_cache_enabled')
                            ->label('Enable Media Caching')
                            ->live()
                            ->helperText('Cache media attachments (images, videos, audio)'),

                        Forms\Components\TextInput::make('media_cache_seconds')
                            ->label('Cache Duration (seconds)')
                            ->numeric()
                            ->required()
                            ->helperText('How long to cache media files (e.g., 86400 = 1 day, 604800 = 1 week)')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('media_cache_enabled')),

                        Forms\Components\TextInput::make('media_path_prefix')
                            ->label('Media Path Prefix')
                            ->helperText('Path prefix for media files (e.g., /storage, /uploads)')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('media_cache_enabled')),
                    ]),
            ]);
    }

    /** @return array<int, Actions\Action> */
    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label('Save Settings')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        try {
            $wantGuestCache = (bool) ($data['guest_cache_enabled'] ?? false);
            $wantMediaCache = (bool) ($data['media_cache_enabled'] ?? false);

            if ($wantGuestCache) {
                if ($this->guestCacheEnabled) {
                    Cloudflare::edgeCaching()->disableGuestCache();
                }
                Cloudflare::edgeCaching()->enableGuestCache((int) ($data['guest_cache_seconds'] ?? 3600));
            } elseif ($this->guestCacheEnabled) {
                Cloudflare::edgeCaching()->disableGuestCache();
            }

            $mediaPrefix = $data['media_path_prefix'] ?? '/storage';

            if ($wantMediaCache) {
                if ($this->mediaCacheEnabled) {
                    Cloudflare::edgeCaching()->disableMediaCache(mediaPathPrefix: $mediaPrefix);
                }
                Cloudflare::edgeCaching()->enableMediaCache(
                    (int) ($data['media_cache_seconds'] ?? 86400),
                    mediaPathPrefix: $mediaPrefix,
                );
            } elseif ($this->mediaCacheEnabled) {
                Cloudflare::edgeCaching()->disableMediaCache(mediaPathPrefix: $mediaPrefix);
            }

            $this->loadCurrentState();

            $this->guestCacheSeconds = (int) ($data['guest_cache_seconds'] ?? $this->guestCacheSeconds);
            $this->mediaCacheSeconds = (int) ($data['media_cache_seconds'] ?? $this->mediaCacheSeconds);
            $this->mediaPathPrefix = $mediaPrefix;

            $this->form->fill([
                'guest_cache_enabled' => $this->guestCacheEnabled,
                'guest_cache_seconds' => $this->guestCacheSeconds,
                'media_cache_enabled' => $this->mediaCacheEnabled,
                'media_cache_seconds' => $this->mediaCacheSeconds,
                'media_path_prefix' => $this->mediaPathPrefix,
            ]);

            Notification::make()
                ->title('Edge Caching settings saved successfully')
                ->success()
                ->send();
        } catch (CloudflareApiException $e) {
            Notification::make()
                ->title('Cloudflare API Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (CloudflareRequestException $e) {
            Notification::make()
                ->title('Network Error')
                ->body('Failed to connect to Cloudflare API: ' . $e->getMessage())
                ->danger()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error')
                ->body('An unexpected error occurred: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
