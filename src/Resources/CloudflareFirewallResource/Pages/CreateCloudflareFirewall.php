<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources\CloudflareFirewallResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use notwonderful\FilamentCloudflare\DataTransferObjects\FirewallRuleData;
use notwonderful\FilamentCloudflare\Enums\FirewallMode;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareException;
use notwonderful\FilamentCloudflare\Facades\Cloudflare;
use notwonderful\FilamentCloudflare\Resources\CloudflareFirewallResource;

/**
 * @property \Filament\Schemas\Schema $form
 */
class CreateCloudflareFirewall extends Page
{
    protected static string $resource = CloudflareFirewallResource::class;
    protected static ?string $title = 'Create Firewall Rule';
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
            ->schema(CloudflareFirewallResource::form(
                Schema::make($this)
            )->getComponents());
    }

    public function create(): void
    {
        $data = $this->form->getState();

        try {
            $ruleData = FirewallRuleData::fromArray($data);
            $payload = $ruleData->toApiPayload();

            Cloudflare::firewall()->createFirewallAccessRule(
                FirewallMode::from($payload['mode']),
                $payload['configuration'],
                $payload['notes'] ?? null
            );

            Notification::make()
                ->title('Firewall rule created successfully')
                ->success()
                ->send();

            $this->redirect(CloudflareFirewallResource::getUrl('index'));
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
                ->url(CloudflareFirewallResource::getUrl('index')),
        ];
    }
}
