<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Pages;

use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;
use notwonderful\FilamentCloudflare\DataTransferObjects\ZoneSettingsData;
use notwonderful\FilamentCloudflare\Enums\MinTlsVersion;
use notwonderful\FilamentCloudflare\Enums\SecurityLevel;
use notwonderful\FilamentCloudflare\Enums\SslMode;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareException;
use notwonderful\FilamentCloudflare\Facades\Cloudflare;

/**
 * @property \Filament\Schemas\Schema $form
 */
class CloudflareSettings extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cloud';
    protected static ?string $navigationLabel = 'Cloudflare Settings';
    protected static \UnitEnum|string|null $navigationGroup = 'Cloudflare';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament-cloudflare::pages.cloudflare-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function getTitle(): string
    {
        return 'Cloudflare Settings';
    }

    public function mount(): void
    {
        $formData = [];

        $zoneId = Cloudflare::getZoneId();

        if ($zoneId) {
            $zoneSettingsResponse = Cloudflare::zone()->getZoneSettings($zoneId);
            $zoneSettings = ZoneSettingsData::fromApiResponse($zoneSettingsResponse);

            $formData = [
                'security_level' => $zoneSettings->securityLevel,
                'ssl' => $zoneSettings->ssl,
                'always_use_https' => $zoneSettings->alwaysUseHttps ?? false,
                'automatic_https_rewrites' => $zoneSettings->automaticHttpsRewrites ?? false,
                'browser_check' => $zoneSettings->browserCheck ?? false,
                'min_tls_version' => $zoneSettings->minTlsVersion,
            ];
        }

        $this->data = $formData;
        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->schema([
                Section::make('Zone Settings')
                    ->description('Manage your Cloudflare zone settings. Credentials are configured via .env file.')
                    ->schema([
                        Forms\Components\Select::make('security_level')
                            ->label('Security Level')
                            ->options(SecurityLevel::options())
                            ->enum(SecurityLevel::class)
                            ->dehydrated(),

                        Forms\Components\Select::make('ssl')
                            ->label('SSL/TLS Encryption Mode')
                            ->options(SslMode::options())
                            ->enum(SslMode::class)
                            ->dehydrated(),

                        Forms\Components\Toggle::make('always_use_https')
                            ->label('Always Use HTTPS')
                            ->dehydrated(),

                        Forms\Components\Toggle::make('automatic_https_rewrites')
                            ->label('Automatic HTTPS Rewrites')
                            ->dehydrated(),

                        Forms\Components\Toggle::make('browser_check')
                            ->label('Browser Integrity Check')
                            ->dehydrated(),

                        Forms\Components\Select::make('min_tls_version')
                            ->label('Minimum TLS Version')
                            ->options(MinTlsVersion::options())
                            ->enum(MinTlsVersion::class)
                            ->dehydrated(),
                    ])
                    ->columns(2),
            ]);
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            if (empty($data)) {
                Notification::make()->title('No data to save')->warning()->send();
                return;
            }

            $zoneId = Cloudflare::getZoneId();

            if (! $zoneId) {
                Notification::make()
                    ->title('Zone ID is not configured')
                    ->body('Set CLOUDFLARE_ZONE_ID in your .env file.')
                    ->danger()
                    ->send();
                return;
            }

            $zoneSettingsData = new ZoneSettingsData(
                securityLevel: $data['security_level'] ?? null,
                ssl: $data['ssl'] ?? null,
                alwaysUseHttps: $data['always_use_https'] ?? null,
                automaticHttpsRewrites: $data['automatic_https_rewrites'] ?? null,
                browserCheck: $data['browser_check'] ?? null,
                minTlsVersion: $data['min_tls_version'] ?? null,
            );

            $zoneSettings = $zoneSettingsData->toApiPayload();

            if (! empty($zoneSettings)) {
                Cloudflare::zone()->updateZoneSettings($zoneSettings, $zoneId);
            }

            $this->mount();

            Notification::make()->title('Settings saved successfully')->success()->send();
        } catch (CloudflareException $e) {
            Log::error('Cloudflare settings save error', ['error' => $e->getMessage()]);
            Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
        }
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('Refresh from Cloudflare')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    try {
                        $this->mount();
                        Notification::make()->title('Settings refreshed from Cloudflare')->success()->send();
                    } catch (CloudflareException $e) {
                        Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('verify')
                ->label('Verify Credentials')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->action(function () {
                    try {
                        Cloudflare::verifyCredentials();
                        Notification::make()
                            ->title('Credentials verified successfully')
                            ->success()
                            ->send();
                    } catch (CloudflareException $e) {
                        Notification::make()->title('Verification failed: ' . $e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('load_zones')
                ->label('Load Zones')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    try {
                        $zones = Cloudflare::zone()->listZones();
                        Notification::make()->title('Found ' . count($zones) . ' zones')->success()->send();
                    } catch (CloudflareException $e) {
                        Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
