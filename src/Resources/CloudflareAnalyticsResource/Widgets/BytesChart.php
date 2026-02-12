<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources\CloudflareAnalyticsResource\Widgets;

use Filament\Widgets\ChartWidget;
use notwonderful\FilamentCloudflare\Resources\CloudflareAnalyticsResource\Widgets\Concerns\HasCloudflareAnalyticsData;

class BytesChart extends ChartWidget
{
    use HasCloudflareAnalyticsData;

    protected ?string $heading = 'Data Served';

    protected ?string $description = 'Total and encrypted bytes served';

    protected function getData(): array
    {
        $zoneData = $this->getAnalyticsData();

        if (!$zoneData) {
            return $this->getEmptyData();
        }

        $zones = $zoneData['zones'] ?? [];
        $labels = [];
        $bytesData = [];
        $encryptedBytesData = [];

        foreach ($zones as $zone) {
            $timeslot = $zone['dimensions']['timeslot'] ?? null;
            if ($timeslot) {
                $labels[] = $this->formatLabel($timeslot);
                $bytesData[] = $zone['sum']['bytes'] ?? 0;
                $encryptedBytesData[] = $zone['sum']['encryptedBytes'] ?? 0;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Bytes',
                    'data' => $bytesData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Encrypted Bytes',
                    'data' => $encryptedBytesData,
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
                    'label' => 'Total Bytes',
                    'data' => [],
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                ],
                [
                    'label' => 'Encrypted Bytes',
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
