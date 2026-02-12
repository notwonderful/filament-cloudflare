<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources\CloudflareAnalyticsResource\Widgets;

use Filament\Widgets\ChartWidget;
use notwonderful\FilamentCloudflare\Resources\CloudflareAnalyticsResource\Widgets\Concerns\HasCloudflareAnalyticsData;

class UniqueVisitorsChart extends ChartWidget
{
    use HasCloudflareAnalyticsData;

    protected ?string $heading = 'Unique Visitors';

    protected ?string $description = 'Number of unique visitors over time';

    protected function getData(): array
    {
        $zoneData = $this->getAnalyticsData();
        
        if (!$zoneData) {
            return $this->getEmptyData();
        }

        $zones = $zoneData['zones'] ?? [];
        $labels = [];
        $uniqueData = [];

        foreach ($zones as $zone) {
            $timeslot = $zone['dimensions']['timeslot'] ?? null;
            if ($timeslot) {
                $labels[] = $this->formatLabel($timeslot);
                $uniqueData[] = $zone['uniq']['uniques'] ?? 0;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Unique Visitors',
                    'data' => $uniqueData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
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
                    'label' => 'Unique Visitors',
                    'data' => [],
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
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
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }

}
