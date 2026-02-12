<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources\CloudflareAnalyticsResource\Widgets;

use Filament\Widgets\ChartWidget;
use notwonderful\FilamentCloudflare\Resources\CloudflareAnalyticsResource\Widgets\Concerns\HasCloudflareAnalyticsData;

class RequestsChart extends ChartWidget
{
    use HasCloudflareAnalyticsData;

    protected ?string $heading = 'Total Requests';

    protected ?string $description = 'Total and encrypted requests over time';

    protected function getData(): array
    {
        $zoneData = $this->getAnalyticsData();

        if (!$zoneData) {
            return $this->getEmptyData();
        }

        $zones = $zoneData['zones'] ?? [];
        $labels = [];
        $requestsData = [];
        $encryptedData = [];

        foreach ($zones as $zone) {
            $timeslot = $zone['dimensions']['timeslot'] ?? null;
            if ($timeslot) {
                $labels[] = $this->formatLabel($timeslot);
                $requestsData[] = $zone['sum']['requests'] ?? 0;
                $encryptedData[] = $zone['sum']['encryptedRequests'] ?? 0;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Requests',
                    'data' => $requestsData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Encrypted Requests',
                    'data' => $encryptedData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.5)',
                    'borderColor' => 'rgba(34, 197, 94, 1)',
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
                    'label' => 'Total Requests',
                    'data' => [],
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                ],
                [
                    'label' => 'Encrypted Requests',
                    'data' => [],
                    'backgroundColor' => 'rgba(34, 197, 94, 0.5)',
                    'borderColor' => 'rgba(34, 197, 94, 1)',
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
