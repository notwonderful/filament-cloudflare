<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources\CloudflareAnalyticsResource\Widgets;

use Filament\Widgets\ChartWidget;
use notwonderful\FilamentCloudflare\Resources\CloudflareAnalyticsResource\Widgets\Concerns\HasCloudflareAnalyticsData;

class CachedBytesChart extends ChartWidget
{
    use HasCloudflareAnalyticsData;

    protected ?string $heading = 'Data Cached';

    protected ?string $description = 'Bytes served from cache';

    protected function getData(): array
    {
        $zoneData = $this->getAnalyticsData();
        
        if (!$zoneData) {
            return $this->getEmptyData();
        }

        $zones = $zoneData['zones'] ?? [];
        $labels = [];
        $cachedBytesData = [];

        foreach ($zones as $zone) {
            $timeslot = $zone['dimensions']['timeslot'] ?? null;
            if ($timeslot) {
                $labels[] = $this->formatLabel($timeslot);
                $cachedBytesData[] = $zone['sum']['cachedBytes'] ?? 0;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Cached Bytes',
                    'data' => $cachedBytesData,
                    'backgroundColor' => 'rgba(168, 85, 247, 0.5)',
                    'borderColor' => 'rgba(168, 85, 247, 1)',
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
                    'label' => 'Cached Bytes',
                    'data' => [],
                    'backgroundColor' => 'rgba(168, 85, 247, 0.5)',
                    'borderColor' => 'rgba(168, 85, 247, 1)',
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
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => "function(value) { 
                            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
                            let unitIndex = 0;
                            let size = value;
                            while (size >= 1024 && unitIndex < units.length - 1) {
                                size /= 1024;
                                unitIndex++;
                            }
                            return size.toFixed(2) + ' ' + units[unitIndex];
                        }",
                    ],
                ],
            ],
        ];
    }

}
