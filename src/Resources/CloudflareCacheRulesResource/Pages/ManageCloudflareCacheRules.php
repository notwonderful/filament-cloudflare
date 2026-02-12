<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources\CloudflareCacheRulesResource\Pages;

use Filament\Actions;
use notwonderful\FilamentCloudflare\Resources\CloudflareCacheRulesResource;
use notwonderful\FilamentCloudflare\Resources\Pages\ApiListRecords;

class ManageCloudflareCacheRules extends ApiListRecords
{
    protected static string $resource = CloudflareCacheRulesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create')
                ->label('New Cache Rule')
                ->icon('heroicon-o-plus')
                ->url(CloudflareCacheRulesResource::getUrl('create')),
            Actions\Action::make('refresh')
                ->label('Refresh Rules')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->dispatch('$refresh')),
        ];
    }
}
