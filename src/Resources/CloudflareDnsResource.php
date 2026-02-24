<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources;

use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use notwonderful\FilamentCloudflare\Enums\DnsRecordType;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareException;
use notwonderful\FilamentCloudflare\Facades\Cloudflare;
use notwonderful\FilamentCloudflare\Resources\CloudflareDnsResource\Pages;

class CloudflareDnsResource extends Resource
{
    protected static ?string $model = null;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationLabel = 'DNS Records';
    protected static \UnitEnum|string|null $navigationGroup = 'Cloudflare';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('DNS Record')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Type')
                            ->options(DnsRecordType::commonOptions())
                            ->enum(DnsRecordType::class)
                            ->required()
                            ->live()
                            ->default(DnsRecordType::A->value),

                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->placeholder('@ or subdomain')
                            ->helperText('Use @ for root domain, or enter a subdomain name')
                            ->required(),

                        Forms\Components\TextInput::make('content')
                            ->label(fn (Get $get) => match ($get('type')) {
                                DnsRecordType::A->value => 'IPv4 Address',
                                DnsRecordType::AAAA->value => 'IPv6 Address',
                                DnsRecordType::CNAME->value => 'Target',
                                DnsRecordType::MX->value => 'Mail Server',
                                DnsRecordType::TXT->value => 'Content',
                                DnsRecordType::NS->value => 'Nameserver',
                                default => 'Content',
                            })
                            ->placeholder(fn (Get $get) => match ($get('type')) {
                                DnsRecordType::A->value => '192.168.1.1',
                                DnsRecordType::AAAA->value => '2001:db8::1',
                                DnsRecordType::CNAME->value => 'target.example.com',
                                DnsRecordType::MX->value => 'mail.example.com',
                                DnsRecordType::TXT->value => 'v=spf1 include:...',
                                default => '',
                            })
                            ->required(),

                        Forms\Components\TextInput::make('priority')
                            ->label('Priority')
                            ->numeric()
                            ->default(10)
                            ->helperText('Lower values mean higher priority')
                            ->visible(fn (Get $get) => in_array(
                                $get('type'),
                                [DnsRecordType::MX->value, DnsRecordType::SRV->value, DnsRecordType::URI->value],
                                true,
                            )),
                    ])
                    ->columns(2),

                Section::make('Settings')
                    ->schema([
                        Forms\Components\Toggle::make('proxied')
                            ->label('Proxied through Cloudflare')
                            ->helperText('Enable Cloudflare proxy (orange cloud) for performance and security')
                            ->default(false)
                            ->visible(fn (Get $get) => in_array(
                                $get('type'),
                                [DnsRecordType::A->value, DnsRecordType::AAAA->value, DnsRecordType::CNAME->value],
                                true,
                            )),

                        Forms\Components\Select::make('ttl')
                            ->label('TTL')
                            ->options([
                                1 => 'Auto',
                                60 => '1 minute',
                                120 => '2 minutes',
                                300 => '5 minutes',
                                600 => '10 minutes',
                                900 => '15 minutes',
                                1800 => '30 minutes',
                                3600 => '1 hour',
                                7200 => '2 hours',
                                18000 => '5 hours',
                                43200 => '12 hours',
                                86400 => '1 day',
                            ])
                            ->default(1)
                            ->required()
                            ->helperText('Time to live — how long DNS resolvers should cache this record'),

                        Forms\Components\TextInput::make('comment')
                            ->label('Comment')
                            ->placeholder('Optional note about this record')
                            ->maxLength(100),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->records(function (): array {
                try {
                    $result = Cloudflare::dns()->listRecords(['per_page' => 500]);

                    return collect($result['records'] ?? [])
                        ->keyBy('id')
                        ->toArray();
                } catch (CloudflareException $e) {
                    Log::warning('Failed to load DNS records', ['error' => $e->getMessage()]);

                    return [];
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state)
                    ->color(fn (string $state): string => DnsRecordType::tryFrom($state)?->color() ?? 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('content')
                    ->label('Content')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\IconColumn::make('proxied')
                    ->label('Proxy')
                    ->boolean()
                    ->trueIcon('heroicon-o-cloud')
                    ->falseIcon('heroicon-o-cloud')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn (bool $state): string => $state ? 'Proxied' : 'DNS only'),

                Tables\Columns\TextColumn::make('ttl')
                    ->label('TTL')
                    ->formatStateUsing(fn (int $state): string => $state === 1 ? 'Auto' : self::formatTtl($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('comment')
                    ->label('Comment')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('modified_on')
                    ->label('Modified')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Record Type')
                    ->options(DnsRecordType::commonOptions()),
            ])
            ->recordActions([
                Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->url(fn (array $record): string => static::getUrl('edit', ['record' => $record['id']])),

                Actions\Action::make('toggle_proxy')
                    ->label(fn (array $record): string => ($record['proxied'] ?? false) ? 'DNS Only' : 'Proxy')
                    ->icon(fn (array $record): string => ($record['proxied'] ?? false) ? 'heroicon-o-cloud' : 'heroicon-o-cloud')
                    ->color(fn (array $record): string => ($record['proxied'] ?? false) ? 'gray' : 'warning')
                    ->visible(fn (array $record): bool => DnsRecordType::tryFrom($record['type'] ?? '')?->supportsProxy() ?? false)
                    ->action(function (array $record) {
                        try {
                            Cloudflare::dns()->updateRecord(
                                $record['id'],
                                DnsRecordType::from($record['type']),
                                $record['name'],
                                $record['content'],
                                $record['ttl'] ?? 1,
                                ! ($record['proxied'] ?? false),
                            );
                            Notification::make()
                                ->title('Proxy status toggled')
                                ->success()
                                ->send();
                        } catch (CloudflareException $e) {
                            Notification::make()
                                ->title('Error: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Actions\Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete DNS Record')
                    ->modalDescription('Are you sure? This will immediately remove the DNS record.')
                    ->action(function (array $record) {
                        try {
                            Cloudflare::dns()->deleteRecord($record['id']);
                            Notification::make()
                                ->title('DNS record deleted')
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
            ->defaultSort('type', 'asc');
    }

    protected static function formatTtl(int $seconds): string
    {
        return match (true) {
            $seconds >= 86400 => round($seconds / 86400) . 'd',
            $seconds >= 3600 => round($seconds / 3600) . 'h',
            $seconds >= 60 => round($seconds / 60) . 'm',
            default => $seconds . 's',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCloudflareDns::route('/'),
            'create' => Pages\CreateCloudflareDns::route('/create'),
            'edit' => Pages\EditCloudflareDns::route('/{record}/edit'),
        ];
    }
}
