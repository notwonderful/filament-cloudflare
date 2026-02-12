<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources\CloudflareAnalyticsResource\Widgets;

use Filament\Widgets\ChartWidget;
use notwonderful\FilamentCloudflare\Resources\CloudflareAnalyticsResource\Widgets\Concerns\HasCloudflareAnalyticsData;

class CachedRequestsPercentChart extends ChartWidget
{
    use HasCloudflareAnalyticsData;

    protected ?string $heading = 'Percent Cached';

    protected ?string $description = 'Percentage of requests served from cache';

    protected function getData(): array
    {
        $zoneData = $this->getAnalyticsData();
        
        if (!$zoneData) {
            return $this->getEmptyData();
        }

        $zones = $zoneData['zones'] ?? [];
        $labels = [];
        $percentData = [];

        foreach ($zones as $zone) {
            $timeslot = $zone['dimensions']['timeslot'] ?? null;
            if ($timeslot) {
                $labels[] = $this->formatLabel($timeslot);
                $requests = $zone['sum']['requests'] ?? 0;
                $cached = $zone['sum']['cachedRequests'] ?? 0;
                $percent = $requests > 0 ? round(($cached / $requests) * 100, 2) : 0;
                $percentData[] = $percent;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Percent Cached',
                    'data' => $percentData,
                    'backgroundColor' => 'rgba(246, 130, 31, 0.5)',
                    'borderColor' => 'rgba(246, 130, 31, 1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /** @return array<string, mixed> */
    protected function getEmptyData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Percent Cached',
                    'data' => [],
                    'backgroundColor' => 'rgba(246, 130, 31, 0.5)',
                    'borderColor' => 'rgba(246, 130, 31, 1)',
                ],
            ],
            'labels' => [],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => "function(context) { return context.parsed.y + '%'; }",
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'max' => 100,
                    'ticks' => [
                        'callback' => "function(value) { return value + '%'; }",
                    ],
                ],
            ],
        ];
    }

}
