<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources\CloudflareDnsResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use notwonderful\FilamentCloudflare\Enums\DnsRecordType;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareException;
use notwonderful\FilamentCloudflare\Facades\Cloudflare;
use notwonderful\FilamentCloudflare\Resources\CloudflareDnsResource;

/**
 * @property \Filament\Schemas\Schema $form
 */
class CreateCloudflareDns extends Page
{
    protected static string $resource = CloudflareDnsResource::class;
    protected static ?string $title = 'Create DNS Record';
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
            ->schema(CloudflareDnsResource::form(
                Schema::make($this)
            )->getComponents());
    }

    public function create(): void
    {
        $data = $this->form->getState();

        try {
            $type = $data['type'] instanceof DnsRecordType
                ? $data['type']
                : DnsRecordType::from($data['type']);

            Cloudflare::dns()->createRecord(
                type: $type,
                name: $data['name'],
                content: $data['content'],
                ttl: (int) ($data['ttl'] ?? 1),
                proxied: (bool) ($data['proxied'] ?? false),
                priority: isset($data['priority']) ? (int) $data['priority'] : null,
                comment: $data['comment'] ?? null,
            );

            Notification::make()
                ->title('DNS record created successfully')
                ->success()
                ->send();

            $this->redirect(CloudflareDnsResource::getUrl('index'));
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
                ->label('Create Record')
                ->submit('create'),

            Actions\Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url(CloudflareDnsResource::getUrl('index')),
        ];
    }
}
