<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Services\EdgeCaching;

use notwonderful\FilamentCloudflare\Contracts\CloudflareClientInterface;
use notwonderful\FilamentCloudflare\Contracts\CloudflareSettingsInterface;
use notwonderful\FilamentCloudflare\Services\Base\CloudflareBaseService;
use notwonderful\FilamentCloudflare\Services\CacheRules\CloudflareCacheRulesService;

class CloudflareEdgeCachingService extends CloudflareBaseService
{
    private const string GUEST_EXPRESSION = '(not http.cookie contains "laravel_session=" and not http.cookie contains "XSRF-TOKEN=" and http.request.method eq "GET" and http.request.uri.query eq "")';

    private const string MEDIA_EXTENSIONS = '.jpg") or ends_with(http.request.uri.path, ".jpeg") or ends_with(http.request.uri.path, ".png") or ends_with(http.request.uri.path, ".gif") or ends_with(http.request.uri.path, ".webp") or ends_with(http.request.uri.path, ".svg") or ends_with(http.request.uri.path, ".mp4") or ends_with(http.request.uri.path, ".webm") or ends_with(http.request.uri.path, ".mp3") or ends_with(http.request.uri.path, ".ogg") or ends_with(http.request.uri.path, ".wav")';

    public function __construct(
        CloudflareClientInterface $client,
        CloudflareSettingsInterface $settings,
        private readonly CloudflareCacheRulesService $cacheRulesService,
    ) {
        parent::__construct($client, $settings);
    }

    /** @return array<string, mixed> */
    public function enableGuestCache(int $seconds, ?string $zoneId = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);

        return $this->cacheRulesService->createCacheRule(
            'Cache guest pages',
            self::GUEST_EXPRESSION,
            $this->buildCacheAction($seconds),
            zoneId: $zoneId,
        );
    }

    public function disableGuestCache(?string $zoneId = null): void
    {
        $this->deleteRuleByExpression(self::GUEST_EXPRESSION, $zoneId);
    }

    public function isGuestCacheEnabled(?string $zoneId = null): bool
    {
        return $this->hasRuleWithExpression(self::GUEST_EXPRESSION, $zoneId);
    }

    /** @return array<string, mixed> */
    public function enableMediaCache(int $seconds, ?string $zoneId = null, ?string $mediaPathPrefix = null): array
    {
        $zoneId = $this->ensureZoneId($zoneId);

        return $this->cacheRulesService->createCacheRule(
            'Cache media attachments',
            $this->mediaExpression($mediaPathPrefix ?? '/storage'),
            $this->buildCacheAction($seconds),
            zoneId: $zoneId,
        );
    }

    public function disableMediaCache(?string $zoneId = null, ?string $mediaPathPrefix = null): void
    {
        $this->deleteRuleByExpression(
            $this->mediaExpression($mediaPathPrefix ?? '/storage'),
            $zoneId,
        );
    }

    public function isMediaCacheEnabled(?string $zoneId = null, ?string $mediaPathPrefix = null): bool
    {
        return $this->hasRuleWithExpression(
            $this->mediaExpression($mediaPathPrefix ?? '/storage'),
            $zoneId,
        );
    }

    private function mediaExpression(string $prefix): string
    {
        return sprintf(
            '(starts_with(http.request.uri.path, "%s") and (ends_with(http.request.uri.path, "%s)))',
            $prefix,
            self::MEDIA_EXTENSIONS,
        );
    }

    /** @return array<string, mixed> */
    private function buildCacheAction(int $seconds): array
    {
        return [
            'cache' => true,
            'edge_ttl' => ['default' => $seconds, 'mode' => 'override_origin'],
            'browser_ttl' => ['default' => $seconds, 'mode' => 'override_origin'],
        ];
    }

    /** @return array<string, mixed>|null */
    private function findRule(string $expression, ?string $zoneId): ?array
    {
        $zoneId = $this->ensureZoneId($zoneId);
        $cacheRules = $this->cacheRulesService->getCacheRules($zoneId);

        $rules = $cacheRules['rules'] ?? [];
        $rulesetId = $cacheRules['id'] ?? null;

        foreach ($rules as $rule) {
            if (($rule['expression'] ?? null) === $expression) {
                return ['rule' => $rule, 'ruleset_id' => $rulesetId];
            }
        }

        return null;
    }

    private function hasRuleWithExpression(string $expression, ?string $zoneId): bool
    {
        return $this->findRule($expression, $zoneId) !== null;
    }

    private function deleteRuleByExpression(string $expression, ?string $zoneId): void
    {
        $found = $this->findRule($expression, $zoneId);

        if ($found && $found['ruleset_id'] && isset($found['rule']['id'])) {
            $this->cacheRulesService->deleteCacheRule(
                $found['ruleset_id'],
                $found['rule']['id'],
                $this->ensureZoneId($zoneId),
            );
        }
    }
}
