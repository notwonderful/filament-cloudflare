<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources\CloudflareAccessResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareException;
use notwonderful\FilamentCloudflare\Facades\Cloudflare;
use notwonderful\FilamentCloudflare\Resources\CloudflareAccessResource;
use notwonderful\FilamentCloudflare\Resources\Pages\ApiListRecords;

class ManageCloudflareAccess extends ApiListRecords
{
    protected static string $resource = CloudflareAccessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create')
                ->label('Create Access App')
                ->icon('heroicon-o-plus')
                ->form([
                    \Filament\Forms\Components\Select::make('type')
                        ->label('Application Type')
                        ->options([
                            'admin' => 'Admin Panel',
                            'install' => 'Install Directory',
                        ])
                        ->required()
                        ->default('admin')
                        ->helperText('Select the type of application to protect'),
                ])
                ->action(function (array $data) {
                    try {
                        Cloudflare::access()->createAdminAccessApp($data['type']);

                        Notification::make()
                            ->title('Access App created successfully')
                            ->body('Your admin panel is now protected by Cloudflare Access')
                            ->success()
                            ->send();

                        $this->dispatch('$refresh');
                    } catch (CloudflareException $e) {
                        Notification::make()
                            ->title('Error: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->dispatch('$refresh')),
        ];
    }
}
