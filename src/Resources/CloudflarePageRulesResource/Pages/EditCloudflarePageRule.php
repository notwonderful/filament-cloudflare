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
class EditCloudflarePageRule extends Page
{
    protected static string $resource = CloudflarePageRulesResource::class;
    protected static ?string $title = 'Edit Page Rule';
    protected string $view = 'filament-cloudflare::pages.resource-create';

    /** @var array<string, mixed>|null */
    public ?array $data = [];
    public string $recordId;

    public function mount(int|string $record): void
    {
        $this->recordId = (string) $record;

        try {
            $rule = Cloudflare::pageRules()->getPageRule($this->recordId);

            if ($rule) {
                $this->form->fill($this->mapRuleToFormData($rule));
            }
        } catch (CloudflareException $e) {
            Notification::make()
                ->title('Error loading page rule: ' . $e->getMessage())
                ->danger()
                ->send();
        }
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

            Cloudflare::pageRules()->updatePageRule(
                $this->recordId,
                $payload['targets'],
                $payload['actions'],
                $payload['priority'],
                PageRuleStatus::from($payload['status'])
            );

            Notification::make()
                ->title('Page rule updated successfully')
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
                ->label('Save')
                ->submit('create'),

            Actions\Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url(CloudflarePageRulesResource::getUrl('index')),
        ];
    }

    /**
     * @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    private function mapRuleToFormData(array $rule): array
    {
        $targetUrl = $rule['targets'][0]['constraint']['value'] ?? '';
        $actions = $this->mapActionsToFormData($rule['actions'] ?? []);

        return [
            'target_url' => $targetUrl,
            'actions' => $actions,
            'priority' => $rule['priority'] ?? 1,
            'status' => $rule['status'] ?? 'active',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $actions
     * @return array<string, mixed>
     */
    private function mapActionsToFormData(array $actions): array
    {
        $formData = [];

        foreach ($actions as $action) {
            $id = $action['id'] ?? '';
            $value = $action['value'] ?? null;

            $mappedValue = match ($id) {
                'forwarding_url' => is_array($value) ? ($value['url'] ?? '') : '',
                'cache_level', 'security_level', 'edge_cache_ttl', 'ssl' => $value,
                'disable_security', 'disable_performance' => (bool) $value,
                default => null,
            };

            if ($mappedValue !== null) {
                $formData[$id] = $mappedValue;
            }
        }

        return $formData;
    }
}
