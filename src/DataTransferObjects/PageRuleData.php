<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\DataTransferObjects;

use notwonderful\FilamentCloudflare\Enums\CacheLevel;
use notwonderful\FilamentCloudflare\Enums\PageRuleStatus;
use notwonderful\FilamentCloudflare\Enums\SecurityLevel;
use notwonderful\FilamentCloudflare\Enums\SslMode;

final readonly class PageRuleData
{
    /**
     * @param array<int, array<string, mixed>> $targets
     * @param array<int, array<string, mixed>> $actions
     */
    public function __construct(
        public array $targets,
        public array $actions,
        public int $priority = 1,
        public PageRuleStatus $status = PageRuleStatus::Active,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $targets = self::buildTargets($data['target_url'] ?? '');
        $actions = self::buildActions($data['actions'] ?? []);

        return new self(
            targets: $targets,
            actions: $actions,
            priority: (int) ($data['priority'] ?? 1),
            status: isset($data['status'])
                ? ($data['status'] instanceof PageRuleStatus ? $data['status'] : PageRuleStatus::from($data['status']))
                : PageRuleStatus::Active,
        );
    }

    /** @return array<string, mixed> */
    public function toApiPayload(): array
    {
        return [
            'targets' => $this->targets,
            'actions' => $this->actions,
            'priority' => $this->priority,
            'status' => $this->status->value,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function buildTargets(string $urlPattern): array
    {
        return empty($urlPattern) ? [] : [[
            'target' => 'url',
            'constraint' => [
                'operator' => 'matches',
                'value' => $urlPattern,
            ],
        ]];
    }

    /**
     * @param array<string, mixed> $actionData
     * @return array<int, array<string, mixed>>
     */
    private static function buildActions(array $actionData): array
    {
        return array_filter([
            !empty($actionData['forwarding_url']) ? [
                'id' => 'forwarding_url',
                'value' => ['status_code' => 301, 'url' => $actionData['forwarding_url']],
            ] : null,
            !empty($actionData['cache_level']) ? [
                'id' => 'cache_level',
                'value' => ($actionData['cache_level'] instanceof CacheLevel ? $actionData['cache_level'] : CacheLevel::from($actionData['cache_level']))->value,
            ] : null,
            !empty($actionData['security_level']) ? [
                'id' => 'security_level',
                'value' => ($actionData['security_level'] instanceof SecurityLevel ? $actionData['security_level'] : SecurityLevel::from($actionData['security_level']))->value,
            ] : null,
            !empty($actionData['disable_security']) ? ['id' => 'disable_security', 'value' => true] : null,
            !empty($actionData['disable_performance']) ? ['id' => 'disable_performance', 'value' => true] : null,
            !empty($actionData['edge_cache_ttl']) ? ['id' => 'edge_cache_ttl', 'value' => (int) $actionData['edge_cache_ttl']] : null,
            !empty($actionData['ssl']) ? [
                'id' => 'ssl',
                'value' => ($actionData['ssl'] instanceof SslMode ? $actionData['ssl'] : SslMode::from($actionData['ssl']))->value,
            ] : null,
        ]);
    }
}
