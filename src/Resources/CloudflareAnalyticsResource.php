<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources;

use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use notwonderful\FilamentCloudflare\Enums\AnalyticsDaysRange;
use notwonderful\FilamentCloudflare\Resources\CloudflareAnalyticsResource\Pages;

class CloudflareAnalyticsResource extends Resource
{
    protected static ?string $model = null;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Analytics';
    protected static \UnitEnum|string|null $navigationGroup = 'Cloudflare';
    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Date Range')
                    ->schema([
                        Forms\Components\Select::make('range')
                            ->label('Time Range')
                            ->options(AnalyticsDaysRange::options())
                            ->default((string) AnalyticsDaysRange::default()->value)
                            ->required()
                            ->dehydrated(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->records(function ($livewire): array {
                if ($livewire instanceof Pages\ManageCloudflareAnalytics) {
                    return $livewire->loadAnalyticsRecords();
                }

                return [];
            })
            ->columns([
                Tables\Columns\TextColumn::make('datetime')
                    ->label('Time')
                    ->getStateUsing(fn (array $record): ?string => $record['datetime'] ?? null)
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('requests')
                    ->label('Requests')
                    ->getStateUsing(fn (array $record): int => $record['requests'] ?? 0)
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cached_requests')
                    ->label('Cached Requests')
                    ->getStateUsing(fn (array $record): int => $record['cached_requests'] ?? 0)
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bytes')
                    ->label('Bytes')
                    ->getStateUsing(fn (array $record) => self::formatBytes($record['bytes'] ?? 0))
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ]);
    }

    protected static function formatBytes(int|float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min((int) $pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCloudflareAnalytics::route('/'),
        ];
    }
}
