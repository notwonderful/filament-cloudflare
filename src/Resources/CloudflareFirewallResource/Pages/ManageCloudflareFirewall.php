<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources\CloudflareFirewallResource\Pages;

use Filament\Actions;
use notwonderful\FilamentCloudflare\Resources\CloudflareFirewallResource;
use notwonderful\FilamentCloudflare\Resources\Pages\ApiListRecords;

class ManageCloudflareFirewall extends ApiListRecords
{
    protected static string $resource = CloudflareFirewallResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create')
                ->label('New Firewall Rule')
                ->icon('heroicon-o-plus')
                ->url(CloudflareFirewallResource::getUrl('create')),
            Actions\Action::make('refresh')
                ->label('Refresh Rules')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->dispatch('$refresh')),
        ];
    }
}
