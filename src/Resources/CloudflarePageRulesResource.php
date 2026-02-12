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
use notwonderful\FilamentCloudflare\Enums\CacheLevel;
use notwonderful\FilamentCloudflare\Enums\PageRuleStatus;
use notwonderful\FilamentCloudflare\Enums\SecurityLevel;
use notwonderful\FilamentCloudflare\Enums\SslMode;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareException;
use notwonderful\FilamentCloudflare\Facades\Cloudflare;
use notwonderful\FilamentCloudflare\Resources\CloudflarePageRulesResource\Pages;

class CloudflarePageRulesResource extends Resource
{
    protected static ?string $model = null;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Page Rules';
    protected static \UnitEnum|string|null $navigationGroup = 'Cloudflare';
    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Target')
                    ->schema([
                        Forms\Components\TextInput::make('target_url')
                            ->label('URL Pattern')
                            ->placeholder('example.com/*')
                            ->helperText('URL pattern to match (e.g., example.com/*, *example.com/css/*)')
                            ->required()
                            ->default('*'),
                    ]),

                Section::make('Actions')
                    ->description('Configure what happens when the URL pattern matches')
                    ->schema([
                        Forms\Components\TextInput::make('actions.forwarding_url')
                            ->label('Forwarding URL')
                            ->placeholder('https://example.com')
                            ->helperText('Redirect to a different URL'),

                        Forms\Components\Select::make('actions.cache_level')
                            ->label('Cache Level')
                            ->options(CacheLevel::options())
                            ->enum(CacheLevel::class)
                            ->helperText('Set the cache level'),

                        Forms\Components\Select::make('actions.security_level')
                            ->label('Security Level')
                            ->options(SecurityLevel::options())
                            ->enum(SecurityLevel::class)
                            ->helperText('Set the security level'),

                        Forms\Components\Toggle::make('actions.disable_security')
                            ->label('Disable Security')
                            ->helperText('Disable security features'),

                        Forms\Components\Toggle::make('actions.disable_performance')
                            ->label('Disable Performance')
                            ->helperText('Disable performance features'),

                        Forms\Components\TextInput::make('actions.edge_cache_ttl')
                            ->label('Edge Cache TTL')
                            ->numeric()
                            ->helperText('Cache TTL in seconds (e.g., 3600 for 1 hour)'),

                        Forms\Components\Select::make('actions.ssl')
                            ->label('SSL')
                            ->options(SslMode::options())
                            ->enum(SslMode::class)
                            ->helperText('SSL/TLS encryption mode'),
                    ])
                    ->columns(2),

                Section::make('Settings')
                    ->schema([
                        Forms\Components\TextInput::make('priority')
                            ->label('Priority')
                            ->numeric()
                            ->default(1)
                            ->helperText('Lower numbers have higher priority')
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(PageRuleStatus::options())
                            ->enum(PageRuleStatus::class)
                            ->default(PageRuleStatus::Active->value)
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->records(function (): array {
                try {
                    $rules = Cloudflare::pageRules()->getPageRules();

                    return collect($rules)
                        ->keyBy('id')
                        ->toArray();
                } catch (CloudflareException $e) {
                    Log::warning('Failed to load page rules', ['error' => $e->getMessage()]);

                    return [];
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('targets')
                    ->label('Targets')
                    ->formatStateUsing(fn (array $state): string => self::formatTargets($state))
                    ->wrap()
                    ->searchable(),

                Tables\Columns\TextColumn::make('actions')
                    ->label('Actions')
                    ->formatStateUsing(fn (array $state): string => self::formatActions($state))
                    ->wrap()
                    ->limit(100),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PageRuleStatus::from($state)->label())
                    ->color(fn (string $state): string => PageRuleStatus::from($state)->color())
                    ->sortable(),

                Tables\Columns\TextColumn::make('priority')
                    ->label('Priority')
                    ->numeric()
                    ->sortable(),

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
                    Tables\Filters\SelectFilter::make('status')
                        ->label('Status')
                        ->options(PageRuleStatus::options()),
                ])
            ->recordActions([
                Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->url(fn (array $record): string => static::getUrl('edit', ['record' => $record['id']])),

                Actions\Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete Page Rule')
                    ->modalDescription('Are you sure you want to delete this page rule? This action cannot be undone.')
                    ->action(function (array $record) {
                        try {
                            Cloudflare::pageRules()->deletePageRule($record['id']);
                            Notification::make()
                                ->title('Page rule deleted successfully')
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
            ->defaultSort('priority', 'desc');
    }

    /** @param array<int, array<string, mixed>> $targets */
    protected static function formatTargets(array $targets): string
    {
        return empty($targets) ? 'N/A' : collect($targets)
            ->map(fn (array $target) => $target['constraint']['value'] ?? '')
            ->filter()
            ->implode(', ');
    }

    /** @param array<int, array<string, mixed>> $actions */
    protected static function formatActions(array $actions): string
    {
        return empty($actions) ? 'N/A' : collect($actions)
            ->map(fn (array $action) => ($action['id'] ?? 'unknown') . ': ' . json_encode($action['value'] ?? ''))
            ->take(3)
            ->implode(', ');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCloudflarePageRules::route('/'),
            'create' => Pages\CreateCloudflarePageRule::route('/create'),
            'edit' => Pages\EditCloudflarePageRule::route('/{record}/edit'),
        ];
    }
}
