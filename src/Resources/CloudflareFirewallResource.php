<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources;

use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use notwonderful\FilamentCloudflare\Enums\FirewallConfigurationType;
use notwonderful\FilamentCloudflare\Enums\FirewallMode;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareException;
use notwonderful\FilamentCloudflare\Facades\Cloudflare;
use notwonderful\FilamentCloudflare\Resources\CloudflareFirewallResource\Pages;

class CloudflareFirewallResource extends Resource
{
    protected static ?string $model = null;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Firewall Rules';
    protected static \UnitEnum|string|null $navigationGroup = 'Cloudflare';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Create Firewall Rule')
                    ->schema([
                        Forms\Components\Select::make('mode')
                            ->label('Action')
                            ->options(FirewallMode::options())
                            ->enum(FirewallMode::class)
                            ->required()
                            ->default(FirewallMode::Block->value),

                        Forms\Components\Select::make('configuration_type')
                            ->label('Configuration Type')
                            ->options(FirewallConfigurationType::options())
                            ->enum(FirewallConfigurationType::class)
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('ip')
                            ->label('IP Address')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('configuration_type') === FirewallConfigurationType::Ip)
                            ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('configuration_type') === FirewallConfigurationType::Ip)
                            ->dehydrated(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('configuration_type') === FirewallConfigurationType::Ip),

                        Forms\Components\TextInput::make('ip_range')
                            ->label('IP Range (CIDR)')
                            ->placeholder('192.168.1.0/24')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('configuration_type') === FirewallConfigurationType::IpRange)
                            ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('configuration_type') === FirewallConfigurationType::IpRange)
                            ->dehydrated(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('configuration_type') === FirewallConfigurationType::IpRange),

                        Forms\Components\Select::make('country')
                            ->label('Country')
                            ->options(self::getCountries())
                            ->searchable()
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('configuration_type') === FirewallConfigurationType::Country)
                            ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('configuration_type') === FirewallConfigurationType::Country)
                            ->dehydrated(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('configuration_type') === FirewallConfigurationType::Country),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->helperText('Optional notes for this rule'),
                    ])
                    ->columns(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->records(function (): array {
                try {
                    $rules = Cloudflare::firewall()->getFirewallAccessRules(1, 100);

                    return collect($rules)
                        ->keyBy('id')
                        ->toArray();
                } catch (CloudflareException $e) {
                    Log::warning('Failed to load firewall rules', ['error' => $e->getMessage()]);

                    return [];
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('mode')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => FirewallMode::from($state)->label())
                    ->color(fn (string $state): string => FirewallMode::from($state)->color())
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('configuration')
                    ->label('Target')
                    ->formatStateUsing(fn ($state, $record) => self::formatConfiguration($record))
                    ->searchable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_on')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('modified_on')
                    ->label('Modified')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('mode')
                    ->label('Action')
                    ->options(FirewallMode::options()),
            ])
            ->recordActions([
                Actions\Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (array $record) {
                        try {
                            Cloudflare::firewall()->deleteFirewallAccessRule($record['id']);
                            Notification::make()
                                ->title('Firewall rule deleted successfully')
                                ->success()
                                ->send();
                        } catch (CloudflareException $e) {
                            Notification::make()
                                ->title('Error: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('created_on', 'desc');
    }

    /** @param array<string, mixed> $record */
    protected static function formatConfiguration(array $record): string
    {
        $config = $record['configuration'] ?? [];

        return $config['value'] ?? $config['target'] ?? 'N/A';
    }

    /**
     * Get ISO 3166-1 alpha-2 country list for firewall rules.
     *
     * Uses the intl extension if available, otherwise falls back to a static list.
     */
    /** @return array<string, string> */
    protected static function getCountries(): array
    {
        if (class_exists(\ResourceBundle::class)) {
            $countries = [];
            $bundle = \ResourceBundle::create('en', 'ICUDATA-region');
            $regionBundle = $bundle['Countries'] ?? null;

            if ($regionBundle !== null) {
                foreach ($regionBundle as $code => $name) {
                    $code = (string) $code;
                    $name = (string) $name;
                    if (strlen($code) === 2 && ctype_alpha($code)) {
                        $countries[$code] = $name;
                    }
                }
                asort($countries);

                return $countries;
            }
        }

        return config('cloudflare.countries', [
            'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AR' => 'Argentina',
            'AU' => 'Australia', 'AT' => 'Austria', 'BD' => 'Bangladesh', 'BY' => 'Belarus',
            'BE' => 'Belgium', 'BR' => 'Brazil', 'BG' => 'Bulgaria', 'CA' => 'Canada',
            'CL' => 'Chile', 'CN' => 'China', 'CO' => 'Colombia', 'HR' => 'Croatia',
            'CZ' => 'Czech Republic', 'DK' => 'Denmark', 'EG' => 'Egypt', 'EE' => 'Estonia',
            'FI' => 'Finland', 'FR' => 'France', 'DE' => 'Germany', 'GR' => 'Greece',
            'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IN' => 'India', 'ID' => 'Indonesia',
            'IR' => 'Iran', 'IQ' => 'Iraq', 'IE' => 'Ireland', 'IL' => 'Israel',
            'IT' => 'Italy', 'JP' => 'Japan', 'KZ' => 'Kazakhstan', 'KE' => 'Kenya',
            'KR' => 'South Korea', 'KP' => 'North Korea', 'LV' => 'Latvia', 'LT' => 'Lithuania',
            'MY' => 'Malaysia', 'MX' => 'Mexico', 'MA' => 'Morocco', 'NL' => 'Netherlands',
            'NZ' => 'New Zealand', 'NG' => 'Nigeria', 'NO' => 'Norway', 'PK' => 'Pakistan',
            'PE' => 'Peru', 'PH' => 'Philippines', 'PL' => 'Poland', 'PT' => 'Portugal',
            'RO' => 'Romania', 'RU' => 'Russia', 'SA' => 'Saudi Arabia', 'RS' => 'Serbia',
            'SG' => 'Singapore', 'SK' => 'Slovakia', 'SI' => 'Slovenia', 'ZA' => 'South Africa',
            'ES' => 'Spain', 'SE' => 'Sweden', 'CH' => 'Switzerland', 'TW' => 'Taiwan',
            'TH' => 'Thailand', 'TR' => 'Turkey', 'UA' => 'Ukraine', 'AE' => 'United Arab Emirates',
            'GB' => 'United Kingdom', 'US' => 'United States', 'VN' => 'Vietnam',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCloudflareFirewall::route('/'),
            'create' => Pages\CreateCloudflareFirewall::route('/create'),
        ];
    }
}
