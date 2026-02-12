<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources\CloudflarePageRulesResource\Pages;

use Filament\Actions;
use notwonderful\FilamentCloudflare\Resources\CloudflarePageRulesResource;
use notwonderful\FilamentCloudflare\Resources\Pages\ApiListRecords;

class ManageCloudflarePageRules extends ApiListRecords
{
    protected static string $resource = CloudflarePageRulesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create')
                ->label('New Page Rule')
                ->icon('heroicon-o-plus')
                ->url(CloudflarePageRulesResource::getUrl('create')),
            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->dispatch('$refresh')),
        ];
    }
}
