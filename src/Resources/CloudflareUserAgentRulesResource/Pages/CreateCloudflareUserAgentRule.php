<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources\CloudflareUserAgentRulesResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use notwonderful\FilamentCloudflare\Enums\FirewallMode;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareException;
use notwonderful\FilamentCloudflare\Facades\Cloudflare;
use notwonderful\FilamentCloudflare\Resources\CloudflareUserAgentRulesResource;

/**
 * @property \Filament\Schemas\Schema $form
 */
class CreateCloudflareUserAgentRule extends Page
{
    protected static string $resource = CloudflareUserAgentRulesResource::class;
    protected static ?string $title = 'Create User Agent Rule';
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
            ->schema(CloudflareUserAgentRulesResource::form(
                Schema::make($this)
            )->getComponents());
    }

    public function create(): void
    {
        $data = $this->form->getState();

        try {
            Cloudflare::firewall()->createFirewallUserAgentRule(
                $data['user_agent'],
                FirewallMode::from($data['mode']),
                $data['description'] ?? null
            );

            Notification::make()
                ->title('User Agent rule created successfully')
                ->success()
                ->send();

            $this->redirect(CloudflareUserAgentRulesResource::getUrl('index'));
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
                ->url(CloudflareUserAgentRulesResource::getUrl('index')),
        ];
    }
}
