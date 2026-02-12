<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources\CloudflareAnalyticsResource\Widgets\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use notwonderful\FilamentCloudflare\Enums\AnalyticsDaysRange;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareApiException;
use notwonderful\FilamentCloudflare\Facades\Cloudflare;

trait HasCloudflareAnalyticsData
{
    public ?int $days = null;

    /** @return array<string, mixed>|null */
    protected function getAnalyticsData(): ?array
    {
        try {
            $days = $this->days ?? $this->getDaysFromPage() ?? AnalyticsDaysRange::default()->value;
            $cacheKey = config('cloudflare.cache.prefix') . ":analytics:{$days}";
            $ttl = config('cloudflare.cache.ttl', 300);

            $analytics = Cache::remember($cacheKey, $ttl, function () use ($days) {
                return Cloudflare::analytics()->getGraphQLAnalytics($days);
            });

            return $analytics['viewer']['zones'][0] ?? null;
        } catch (CloudflareApiException $e) {
            Log::warning('Cloudflare Analytics API Error', [
                'error' => $e->getMessage(),
                'errors' => $e->getErrors(),
            ]);
            return null;
        } catch (\Throwable $e) {
            Log::error('Cloudflare Analytics Error', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function getDaysFromPage(): ?int
    {
        try {
            /** @var object|null $page */
            $page = method_exists($this, 'getPage') ? $this->getPage() : null;
            return match (true) {
                $page !== null && method_exists($page, 'getDays') => $page->getDays(),
                $page !== null && property_exists($page, 'days') => $page->days ?? null,
                default => null,
            };
        } catch (\Exception) {
            return null;
        }
    }

    protected function formatLabel(string $timeslot): string
    {
        $timestamp = strtotime($timeslot);
        if ($timestamp === false) {
            return $timeslot;
        }

        return str_contains($timeslot, 'T')
            ? date('H:i', $timestamp)
            : date('M d', $timestamp);
    }
}
