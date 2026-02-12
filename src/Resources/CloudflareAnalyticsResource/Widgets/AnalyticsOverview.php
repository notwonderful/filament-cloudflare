<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources\CloudflareAnalyticsResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Log;
use notwonderful\FilamentCloudflare\Concerns\FormatsBytes;
use notwonderful\FilamentCloudflare\Exceptions\CloudflareApiException;
use notwonderful\FilamentCloudflare\Resources\CloudflareAnalyticsResource\Widgets\Concerns\HasCloudflareAnalyticsData;

class AnalyticsOverview extends BaseWidget
{
    use HasCloudflareAnalyticsData;
    use FormatsBytes;

    /** @return array<string, int> */
    protected function getData(): array
    {
        try {
            $zoneData = $this->getAnalyticsData();

            if (!$zoneData) {
                return $this->getEmptyData();
            }

            $zones = $zoneData['zones'] ?? [];
            $totals = $zoneData['totals'][0] ?? null;

            $totalRequests = 0;
            $totalCached = 0;
            $totalBytes = 0;

            foreach ($zones as $zone) {
                $totalRequests += $zone['sum']['requests'] ?? 0;
                $totalCached += $zone['sum']['cachedRequests'] ?? 0;
                $totalBytes += $zone['sum']['bytes'] ?? 0;
            }

            return [
                'total_requests' => $totalRequests,
                'total_cached' => $totalCached,
                'total_bytes' => $totalBytes,
                'total_uniques' => $totals['uniq']['uniques'] ?? 0,
            ];
        } catch (CloudflareApiException $e) {
            Log::warning('AnalyticsOverview Cloudflare API Error', [
                'error' => $e->getMessage(),
                'errors' => $e->getErrors(),
            ]);
            return $this->getEmptyData();
        } catch (\Throwable $e) {
            Log::error('AnalyticsOverview getData error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->getEmptyData();
        }
    }

    /** @return array<string, int> */
    protected function getEmptyData(): array
    {
        return [
            'total_requests' => 0,
            'total_cached' => 0,
            'total_bytes' => 0,
            'total_uniques' => 0,
        ];
    }

    protected function getStats(): array
    {
        $data = $this->getData();

        return [
            Stat::make('Total Requests', number_format($data['total_requests'] ?? 0))
                ->description('All HTTP requests')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Cached Requests', number_format($data['total_cached'] ?? 0))
                ->description('Requests served from cache')
                ->descriptionIcon('heroicon-m-bolt')
                ->color('info'),

            Stat::make('Total Bandwidth', $this->formatBytes($data['total_bytes'] ?? 0))
                ->description('Data transferred')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('warning'),

            Stat::make('Unique Visitors', number_format($data['total_uniques'] ?? 0))
                ->description('Unique IP addresses')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
        ];
    }
}
