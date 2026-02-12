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

    public ?bool $guestCacheEnabled = false;
    public ?int $guestCacheSeconds = 3600;
    public ?bool $mediaCacheEnabled = false;
    public ?int $mediaCacheSeconds = 86400;
    public ?string $mediaPathPrefix = '/storage';

    protected function getGuestCacheStatus(): bool
    {
        try {
            return Cloudflare::edgeCaching()->isGuestCacheEnabled();
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function getMediaCacheStatus(): bool
    {
        try {
            return Cloudflare::edgeCaching()->isMediaCacheEnabled();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getTitle(): string
    {
        return 'Edge Caching';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Guest Page Caching')
                    ->description('Cache HTML pages for guests (users without session cookies) at Cloudflare edge locations')
                    ->schema([
                        Forms\Components\Toggle::make('guest_cache_enabled')
                            ->label('Enable Guest Page Caching')
                            ->live()
                            ->default(fn () => $this->getGuestCacheStatus())
                            ->helperText('Cache pages for users who are not logged in'),

                        Forms\Components\TextInput::make('guest_cache_seconds')
                            ->label('Cache Duration (seconds)')
                            ->numeric()
                            ->required()
                            ->default(fn () => $this->guestCacheSeconds)
                            ->helperText('How long to cache pages (e.g., 3600 = 1 hour, 86400 = 1 day)')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('guest_cache_enabled')),
                    ]),

                Section::make('Media Attachment Caching')
                    ->description('Cache media files (images, videos, audio) at Cloudflare edge locations')
                    ->schema([
                        Forms\Components\Toggle::make('media_cache_enabled')
                            ->label('Enable Media Caching')
                            ->live()
                            ->default(fn () => $this->getMediaCacheStatus())
                            ->helperText('Cache media attachments (images, videos, audio)'),

                        Forms\Components\TextInput::make('media_cache_seconds')
                            ->label('Cache Duration (seconds)')
                            ->numeric()
                            ->required()
                            ->default(fn () => $this->mediaCacheSeconds)
                            ->helperText('How long to cache media files (e.g., 86400 = 1 day, 604800 = 1 week)')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('media_cache_enabled')),

                        Forms\Components\TextInput::make('media_path_prefix')
                            ->label('Media Path Prefix')
                            ->default(fn () => $this->mediaPathPrefix)
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
            // Handle guest cache
            if ($data['guest_cache_enabled'] ?? false) {
                if ($this->guestCacheEnabled) {
                    // Already enabled, update if needed
                    Cloudflare::edgeCaching()->disableGuestCache();
                }
                Cloudflare::edgeCaching()->enableGuestCache((int) ($data['guest_cache_seconds'] ?? 3600));
            } else {
                if ($this->guestCacheEnabled) {
                    Cloudflare::edgeCaching()->disableGuestCache();
                }
            }

            // Handle media cache
            if ($data['media_cache_enabled'] ?? false) {
                if ($this->mediaCacheEnabled) {
                    // Already enabled, update if needed
                    Cloudflare::edgeCaching()->disableMediaCache();
                }
                Cloudflare::edgeCaching()->enableMediaCache(
                    (int) ($data['media_cache_seconds'] ?? 86400),
                    null,
                    $data['media_path_prefix'] ?? '/storage'
                );
            } else {
                if ($this->mediaCacheEnabled) {
                    Cloudflare::edgeCaching()->disableMediaCache();
                }
            }

            Notification::make()
                ->title('Edge Caching settings saved successfully')
                ->success()
                ->send();

            // Refresh status
            $this->guestCacheEnabled = Cloudflare::edgeCaching()->isGuestCacheEnabled();
            $this->mediaCacheEnabled = Cloudflare::edgeCaching()->isMediaCacheEnabled();
            
            $this->form->fill([
                'guest_cache_enabled' => $this->guestCacheEnabled,
                'guest_cache_seconds' => $data['guest_cache_seconds'] ?? $this->guestCacheSeconds,
                'media_cache_enabled' => $this->mediaCacheEnabled,
                'media_cache_seconds' => $data['media_cache_seconds'] ?? $this->mediaCacheSeconds,
                'media_path_prefix' => $data['media_path_prefix'] ?? $this->mediaPathPrefix,
            ]);
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
