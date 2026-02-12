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
        $settings = Cloudflare::settings()->getAll();

        $formData = [
            'cloudflare_email' => $settings['email'] ?? null,
            'cloudflare_api_key' => $settings['api_key'] ?? null,
            'cloudflare_token' => $settings['token'] ?? null,
            'cloudflare_zone_id' => $settings['zone_id'] ?? null,
            'cloudflare_account_id' => $settings['account_id'] ?? null,
        ];

        try {
            $zoneId = $formData['cloudflare_zone_id'];

            if ($zoneId) {
                $zoneSettingsResponse = Cloudflare::zone()->getZoneSettings($zoneId);
                $zoneSettings = ZoneSettingsData::fromApiResponse($zoneSettingsResponse);

                $formData = array_merge($formData, [
                    'security_level' => $zoneSettings->securityLevel,
                    'ssl' => $zoneSettings->ssl,
                    'always_use_https' => $zoneSettings->alwaysUseHttps ?? false,
                    'automatic_https_rewrites' => $zoneSettings->automaticHttpsRewrites ?? false,
                    'browser_check' => $zoneSettings->browserCheck ?? false,
                    'min_tls_version' => $zoneSettings->minTlsVersion,
                ]);
            }
        } catch (CloudflareException $e) {
            Log::warning('Failed to load Cloudflare zone settings', ['error' => $e->getMessage()]);
        }

        $this->data = $formData;
        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->schema([
                Section::make('Authentication')
                    ->description('Configure your Cloudflare API credentials')
                    ->schema([
                        Forms\Components\TextInput::make('cloudflare_email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->dehydrated()
                            ->helperText('Your Cloudflare account email address'),

                        Forms\Components\TextInput::make('cloudflare_api_key')
                            ->label('API Key')
                            ->password()
                            ->required()
                            ->dehydrated()
                            ->helperText('Your Cloudflare Global API Key'),

                        Forms\Components\TextInput::make('cloudflare_token')
                            ->label('API Token')
                            ->password()
                            ->dehydrated()
                            ->helperText('API Token is recommended for better security and granular permissions'),

                        Forms\Components\TextInput::make('cloudflare_zone_id')
                            ->label('Zone ID')
                            ->required()
                            ->dehydrated()
                            ->live(onBlur: true)
                            ->helperText('The Zone ID for your Cloudflare zone'),

                        Forms\Components\TextInput::make('cloudflare_account_id')
                            ->label('Account ID')
                            ->dehydrated()
                            ->helperText('Your Cloudflare account ID (optional)'),
                    ])
                    ->columns(2),

                Section::make('Zone Settings')
                    ->description('Manage your Cloudflare zone settings')
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

            Cloudflare::settings()->setAll([
                'email' => $data['cloudflare_email'] ?? null,
                'api_key' => $data['cloudflare_api_key'] ?? null,
                'token' => $data['cloudflare_token'] ?? null,
                'zone_id' => $data['cloudflare_zone_id'] ?? null,
                'account_id' => $data['cloudflare_account_id'] ?? null,
            ]);

            $zoneId = $data['cloudflare_zone_id'] ?? null;

            if (! $zoneId) {
                Notification::make()->title('Zone ID is required')->danger()->send();
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
                        $this->form->fill($this->data);
                        Notification::make()->title('Settings refreshed from Cloudflare')->success()->send();
                        $this->dispatch('$refresh');
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
                        Notification::make()->title('Found ' . count($zones)  . ' zones')->success()->send();
                    } catch (CloudflareException $e) {
                        Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
