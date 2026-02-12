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
use notwonderful\FilamentCloudflare\Exceptions\CloudflareException;
use notwonderful\FilamentCloudflare\Facades\Cloudflare;
use notwonderful\FilamentCloudflare\Resources\CloudflareCacheRulesResource\Pages;

class CloudflareCacheRulesResource extends Resource
{
    protected static ?string $model = null;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = 'Cache Rules';
    protected static \UnitEnum|string|null $navigationGroup = 'Cloudflare';
    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Create Cache Rule')
                    ->schema([
                        Forms\Components\TextInput::make('description')
                            ->label('Description')
                            ->required()
                            ->maxLength(255)
                            ->helperText('A description for this cache rule'),

                        Forms\Components\Textarea::make('expression')
                            ->label('Expression')
                            ->required()
                            ->rows(5)
                            ->placeholder('(http.request.uri.path matches "^/css/")')
                            ->helperText('Cloudflare Expression Language. Example: (http.request.uri.path matches "^/css/")')
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('cache')
                            ->label('Enable Caching')
                            ->default(true)
                            ->helperText('Enable caching for matching requests'),

                        Forms\Components\Select::make('cache_level')
                            ->label('Cache Level')
                            ->options([
                                'bypass' => 'Bypass',
                                'basic' => 'Basic',
                                'simplified' => 'Simplified',
                                'aggressive' => 'Aggressive',
                                'cache_everything' => 'Cache Everything',
                            ])
                            ->helperText('Cache level for matching requests'),

                        Forms\Components\TextInput::make('edge_ttl')
                            ->label('Edge TTL (seconds)')
                            ->numeric()
                            ->helperText('Time to live at the edge (0 = respect origin)'),

                        Forms\Components\TextInput::make('browser_ttl')
                            ->label('Browser TTL (seconds)')
                            ->numeric()
                            ->helperText('Time to live in browser (0 = respect origin)'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->records(function (): array {
                try {
                    $response = Cloudflare::cacheRules()->getCacheRules();
                    $rules = $response['rules'] ?? [];
                    $rulesetId = $response['id'] ?? null;

                    return collect($rules)
                        ->map(fn (array $rule) => [...$rule, 'ruleset_id' => $rulesetId])
                        ->keyBy('id')
                        ->toArray();
                } catch (CloudflareException $e) {
                    Log::warning('Failed to load cache rules', ['error' => $e->getMessage()]);

                    return [];
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50)
                    ->sortable(),

                Tables\Columns\TextColumn::make('expression')
                    ->label('Expression')
                    ->limit(50)
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Expression copied')
                    ->copyMessageDuration(1500),

                Tables\Columns\IconColumn::make('enabled')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('action_parameters')
                    ->label('Cache Settings')
                    ->formatStateUsing(fn (array $state): string => self::formatActionParameters($state))
                    ->limit(100)
                    ->wrap(),

                Tables\Columns\TextColumn::make('last_updated')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('enabled')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Enabled')
                    ->falseLabel('Disabled'),
            ])
            ->recordActions([
                Actions\Action::make('toggle')
                    ->label(fn (array $record): string => $record['enabled'] ? 'Disable' : 'Enable')
                    ->icon(fn (array $record): string => $record['enabled'] ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (array $record): string => $record['enabled'] ? 'danger' : 'success')
                    ->action(function (array $record) {
                        try {
                            $rulesetId = $record['ruleset_id'] ?? null;
                            if (!$rulesetId) {
                                throw new \Exception('Ruleset ID not found');
                            }

                            Cloudflare::cacheRules()->updateCacheRule(
                                $rulesetId,
                                $record['id'],
                                $record['description'],
                                $record['expression'],
                                $record['action_parameters'],
                                !$record['enabled']
                            );

                            Notification::make()
                                ->title('Cache rule ' . (!$record['enabled'] ? 'enabled' : 'disabled') . ' successfully')
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
                    ->action(function (array $record) {
                        try {
                            $rulesetId = $record['ruleset_id'] ?? null;
                            if (!$rulesetId) {
                                throw new \Exception('Ruleset ID not found');
                            }

                            Cloudflare::cacheRules()->deleteCacheRule($rulesetId, $record['id']);
                            Notification::make()
                                ->title('Cache rule deleted successfully')
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
            ->defaultSort('last_updated', 'desc');
    }

    /** @param array<string, mixed> $actionParameters */
    protected static function formatActionParameters(array $actionParameters): string
    {
        $parts = [];

        if (isset($actionParameters['cache']) && $actionParameters['cache']) {
            $parts[] = 'Cache: Enabled';
        }

        if (isset($actionParameters['cache_level'])) {
            $parts[] = 'Level: ' . ucfirst($actionParameters['cache_level']);
        }

        if (isset($actionParameters['edge_ttl'])) {
            $ttl = is_array($actionParameters['edge_ttl']) 
                ? ($actionParameters['edge_ttl']['default'] ?? 'N/A')
                : $actionParameters['edge_ttl'];
            $parts[] = 'Edge TTL: ' . ($ttl > 0 ? $ttl . 's' : 'Respect Origin');
        }

        if (isset($actionParameters['browser_ttl'])) {
            $ttl = is_array($actionParameters['browser_ttl']) 
                ? ($actionParameters['browser_ttl']['default'] ?? 'N/A')
                : $actionParameters['browser_ttl'];
            $parts[] = 'Browser TTL: ' . ($ttl > 0 ? $ttl . 's' : 'Respect Origin');
        }

        return $parts ? implode(', ', $parts) : 'No settings';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCloudflareCacheRules::route('/'),
            'create' => Pages\CreateCloudflareCacheRule::route('/create'),
        ];
    }
}
