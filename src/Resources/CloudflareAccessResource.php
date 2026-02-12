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

class CloudflareAccessResource extends Resource
{
    protected static ?string $model = null;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-lock-closed';
    protected static ?string $navigationLabel = 'Access';
    protected static \UnitEnum|string|null $navigationGroup = 'Cloudflare';
    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Create Access App')
                    ->description('Protect your admin panel or install directory with Cloudflare Access')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Application Type')
                            ->options([
                                'admin' => 'Admin Panel',
                                'install' => 'Install Directory',
                            ])
                            ->required()
                            ->default('admin')
                            ->helperText('Select the type of application to protect'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->records(function (): array {
                try {
                    $apps = Cloudflare::access()->getAccessApps();

                    return collect($apps)->keyBy('id')->toArray();
                } catch (CloudflareException $e) {
                    Log::warning('Failed to load access apps', ['error' => $e->getMessage()]);

                    return [];
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('domain')
                    ->label('Domain')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'self_hosted' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Actions\Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (array $record) {
                        try {
                            Cloudflare::access()->deleteAccessApp($record['id']);

                            Notification::make()
                                ->title('Access App deleted successfully')
                                ->success()
                                ->send();
                        } catch (CloudflareException $e) {
                            Notification::make()
                                ->title('Error: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \notwonderful\FilamentCloudflare\Resources\CloudflareAccessResource\Pages\ManageCloudflareAccess::route('/'),
        ];
    }
}
