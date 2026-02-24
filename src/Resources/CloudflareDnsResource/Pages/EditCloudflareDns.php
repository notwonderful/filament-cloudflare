<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources\CloudflareDnsResource\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use notwonderful\FilamentCloudflare\Enums\DnsRecordType;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareException;
use notwonderful\FilamentCloudflare\Facades\Cloudflare;
use notwonderful\FilamentCloudflare\Resources\CloudflareDnsResource;

/**
 * @property Schema $form
 */
class EditCloudflareDns extends Page
{
    protected static string $resource = CloudflareDnsResource::class;
    protected static ?string $title = 'Edit DNS Record';
    protected string $view = 'filament-cloudflare::pages.resource-create';

    /** @var array<string, mixed>|null */
    public ?array $data = [];
    public string $recordId;

    public function mount(int|string $record): void
    {
        $this->recordId = (string) $record;

        try {
            $dnsRecord = Cloudflare::dns()->getRecord($this->recordId);

            if ($dnsRecord) {
                $this->form->fill([
                    'type' => $dnsRecord['type'] ?? null,
                    'name' => $dnsRecord['name'] ?? null,
                    'content' => $dnsRecord['content'] ?? null,
                    'ttl' => $dnsRecord['ttl'] ?? 1,
                    'proxied' => $dnsRecord['proxied'] ?? false,
                    'priority' => $dnsRecord['priority'] ?? null,
                    'comment' => $dnsRecord['comment'] ?? null,
                ]);
            }
        } catch (CloudflareException $e) {
            Notification::make()
                ->title('Error loading DNS record: ' . $e->getMessage())
                ->danger()
                ->send();
        }
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

            Cloudflare::dns()->updateRecord(
                recordId: $this->recordId,
                type: $type,
                name: $data['name'],
                content: $data['content'],
                ttl: (int) ($data['ttl'] ?? 1),
                proxied: (bool) ($data['proxied'] ?? false),
                priority: isset($data['priority']) ? (int) $data['priority'] : null,
                comment: $data['comment'] ?? null,
            );

            Notification::make()
                ->title('DNS record updated successfully')
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

    /** @return array<int, Action> */
    protected function getFormActions(): array
    {
        return [
            Action::make('create')
                ->label('Save')
                ->submit('create'),

            Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url(CloudflareDnsResource::getUrl('index')),
        ];
    }
}
