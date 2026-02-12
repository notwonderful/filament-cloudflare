<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources\CloudflareUserAgentRulesResource\Pages;

use Filament\Actions;
use notwonderful\FilamentCloudflare\Resources\CloudflareUserAgentRulesResource;
use notwonderful\FilamentCloudflare\Resources\Pages\ApiListRecords;

class ManageCloudflareUserAgentRules extends ApiListRecords
{
    protected static string $resource = CloudflareUserAgentRulesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create')
                ->label('New User Agent Rule')
                ->icon('heroicon-o-plus')
                ->url(CloudflareUserAgentRulesResource::getUrl('create')),
            Actions\Action::make('refresh')
                ->label('Refresh Rules')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->dispatch('$refresh')),
        ];
    }
}
