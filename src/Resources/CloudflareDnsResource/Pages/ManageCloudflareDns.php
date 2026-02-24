<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources\CloudflareDnsResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareException;
use notwonderful\FilamentCloudflare\Facades\Cloudflare;
use notwonderful\FilamentCloudflare\Resources\CloudflareDnsResource;
use notwonderful\FilamentCloudflare\Resources\Pages\ApiListRecords;

class ManageCloudflareDns extends ApiListRecords
{
    protected static string $resource = CloudflareDnsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create')
                ->label('New Record')
                ->icon('heroicon-o-plus')
                ->url(CloudflareDnsResource::getUrl('create')),

            Actions\Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    try {
                        $content = Cloudflare::dns()->exportRecords();

                        Notification::make()
                            ->title('DNS records exported (' . strlen($content) . ' bytes)')
                            ->success()
                            ->send();

                        return response()->streamDownload(function () use ($content) {
                            echo $content;
                        }, 'dns-records.txt', [
                            'Content-Type' => 'text/plain',
                        ]);
                    } catch (CloudflareException $e) {
                        Notification::make()
                            ->title('Export failed: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->dispatch('$refresh')),
        ];
    }
}
