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
use notwonderful\FilamentCloudflare\Enums\FirewallMode;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareException;
use notwonderful\FilamentCloudflare\Facades\Cloudflare;
use notwonderful\FilamentCloudflare\Resources\CloudflareUserAgentRulesResource\Pages;

class CloudflareUserAgentRulesResource extends Resource
{
    protected static ?string $model = null;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-exclamation';
    protected static ?string $navigationLabel = 'User Agent Rules';
    protected static \UnitEnum|string|null $navigationGroup = 'Cloudflare';
    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Create User Agent Rule')
                    ->schema([
                        Forms\Components\TextInput::make('user_agent')
                            ->label('User Agent')
                            ->required()
                            ->placeholder('Mozilla/5.0 (compatible; Googlebot/2.1)')
                            ->helperText('Enter the User Agent string to match'),

                        Forms\Components\Select::make('mode')
                            ->label('Action')
                            ->options(FirewallMode::options())
                            ->enum(FirewallMode::class)
                            ->required()
                            ->default(FirewallMode::Block->value),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->helperText('Optional description for this rule'),
                    ])
                    ->columns(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->records(function (): array {
                try {
                    $allRules = [];
                    $page = 1;

                    do {
                        $result = Cloudflare::firewall()->getFirewallUserAgentRules($page, 1000);
                        $allRules = array_merge($allRules, $result->items);
                        $page++;
                    } while ($page <= $result->totalPages());

                    return collect($allRules)->keyBy('id')->toArray();
                } catch (CloudflareException $e) {
                    Log::warning('Failed to load user agent rules', ['error' => $e->getMessage()]);

                    return [];
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('configuration.value')
                    ->label('User Agent')
                    ->searchable()
                    ->limit(50)
                    ->sortable(),

                Tables\Columns\TextColumn::make('mode')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => FirewallMode::from($state)->label())
                    ->color(fn (string $state): string => FirewallMode::from($state)->color())
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('paused')
                    ->label('Status')
                    ->boolean()
                    ->getStateUsing(fn (array $record): bool => !($record['paused'] ?? false))
                    ->trueIcon('heroicon-o-play-circle')
                    ->falseIcon('heroicon-o-pause-circle')
                    ->trueColor('success')
                    ->falseColor('warning'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
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

                Tables\Filters\TernaryFilter::make('paused')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Paused')
                    ->falseLabel('Active'),
            ])
            ->recordActions([
                Actions\Action::make('toggle')
                    ->label(fn (array $record): string => $record['paused'] ? 'Enable' : 'Disable')
                    ->icon(fn (array $record): string => $record['paused'] ? 'heroicon-o-play-circle' : 'heroicon-o-pause-circle')
                    ->color(fn (array $record): string => $record['paused'] ? 'success' : 'warning')
                    ->action(function (array $record) {
                        try {
                            $paused = !$record['paused'];
                            $userAgent = $record['configuration']['value'] ?? '';
                            $description = $record['description'] ?? null;

                            Cloudflare::firewall()->updateFirewallUserAgentRule(
                                $record['id'],
                                FirewallMode::from($record['mode']),
                                $userAgent,
                                $description,
                                $paused
                            );

                            Notification::make()
                                ->title('User Agent rule ' . ($paused ? 'disabled' : 'enabled') . ' successfully')
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
                            Cloudflare::firewall()->deleteFirewallUserAgentRule($record['id']);
                            Notification::make()
                                ->title('User Agent rule deleted successfully')
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCloudflareUserAgentRules::route('/'),
            'create' => Pages\CreateCloudflareUserAgentRule::route('/create'),
        ];
    }
}
