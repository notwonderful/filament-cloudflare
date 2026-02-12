<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources\CloudflareCacheRulesResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareException;
use notwonderful\FilamentCloudflare\Facades\Cloudflare;
use notwonderful\FilamentCloudflare\Resources\CloudflareCacheRulesResource;

/**
 * @property \Filament\Schemas\Schema $form
 */
class CreateCloudflareCacheRule extends Page
{
    protected static string $resource = CloudflareCacheRulesResource::class;
    protected static ?string $title = 'Create Cache Rule';
    protected string $view = 'filament-cloudflare::pages.resource-create';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->schema(CloudflareCacheRulesResource::form(
                Schema::make($this)
            )->getComponents());
    }

    public function create(): void
    {
        $data = $this->form->getState();

        try {
            $actionParameters = [];

            if (! empty($data['cache'])) {
                $actionParameters['cache'] = true;
            }

            if (! empty($data['cache_level'])) {
                $actionParameters['cache_level'] = $data['cache_level'];
            }

            if (! empty($data['edge_ttl'])) {
                $actionParameters['edge_ttl'] = [
                    'default' => (int) $data['edge_ttl'],
                    'mode' => 'override_origin',
                ];
            }

            if (! empty($data['browser_ttl'])) {
                $actionParameters['browser_ttl'] = [
                    'default' => (int) $data['browser_ttl'],
                    'mode' => 'override_origin',
                ];
            }

            $cacheRules = Cloudflare::cacheRules()->getCacheRules();
            $rulesetId = $cacheRules['id'] ?? null;

            Cloudflare::cacheRules()->createCacheRule(
                $data['description'],
                $data['expression'],
                $actionParameters,
                $rulesetId
            );

            Notification::make()
                ->title('Cache rule created successfully')
                ->success()
                ->send();

            $this->redirect(CloudflareCacheRulesResource::getUrl('index'));
        } catch (CloudflareException $e) {
            Notification::make()
                ->title('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /** @return array<int, Actions\Action> */
    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('create')
                ->label('Create')
                ->submit('create'),

            Actions\Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url(CloudflareCacheRulesResource::getUrl('index')),
        ];
    }
}
