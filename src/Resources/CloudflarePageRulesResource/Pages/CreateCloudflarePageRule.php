<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources\CloudflarePageRulesResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use notwonderful\FilamentCloudflare\DataTransferObjects\PageRuleData;
use notwonderful\FilamentCloudflare\Enums\PageRuleStatus;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareException;
use notwonderful\FilamentCloudflare\Facades\Cloudflare;
use notwonderful\FilamentCloudflare\Resources\CloudflarePageRulesResource;

/**
 * @property \Filament\Schemas\Schema $form
 */
class CreateCloudflarePageRule extends Page
{
    protected static string $resource = CloudflarePageRulesResource::class;
    protected static ?string $title = 'Create Page Rule';
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
            ->schema(CloudflarePageRulesResource::form(
                Schema::make($this)
            )->getComponents());
    }

    public function create(): void
    {
        $data = $this->form->getState();

        try {
            $ruleData = PageRuleData::fromArray($data);
            $payload = $ruleData->toApiPayload();

            Cloudflare::pageRules()->createPageRule(
                $payload['targets'],
                $payload['actions'],
                $payload['priority'],
                PageRuleStatus::from($payload['status'])
            );

            Notification::make()
                ->title('Page rule created successfully')
                ->success()
                ->send();

            $this->redirect(CloudflarePageRulesResource::getUrl('index'));
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
                ->url(CloudflarePageRulesResource::getUrl('index')),
        ];
    }
}
