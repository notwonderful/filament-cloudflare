<?php

declare(strict_types=1);

namespace notwonderful\FilamentCloudflare\Resources\CloudflareAnalyticsResource\Pages;

use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use notwonderful\FilamentCloudflare\Enums\AnalyticsDaysRange;
use notwonderful\FilamentCloudflare\Facades\Cloudflare;
use notwonderful\FilamentCloudflare\Resources\CloudflareAnalyticsResource;
use notwonderful\FilamentCloudflare\Resources\Pages\ApiListRecords;

class ManageCloudflareAnalytics extends ApiListRecords
{
    protected static string $resource = CloudflareAnalyticsResource::class;

    public int $days;

    public function mount(): void
    {
        parent::mount();

        $this->days = AnalyticsDaysRange::default()->value;

        if (request()->has('days')) {
            $daysValue = (int) request()->query('days');

            if (AnalyticsDaysRange::tryFrom($daysValue) !== null) {
                $this->days = $daysValue;
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->dispatch('$refresh')),

            Actions\Action::make('change_range')
                ->label('Change Range')
                ->icon('heroicon-o-calendar')
                ->schema([
                    Forms\Components\Select::make('days')
                        ->label('Days')
                        ->options(AnalyticsDaysRange::options())
                        ->default($this->days)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $daysRange = AnalyticsDaysRange::from((int) $data['days']);
                    $this->days = $daysRange->value;
                    $this->redirect(static::getUrl(['days' => $this->days]));
                }),
        ];
    }

    public function getDays(): int
    {
        return $this->days;
    }

    /**
     * Load analytics data for the table.
     *
     * Called from the Resource's records() closure which receives this page as $livewire.
     */
    /** @return array<string, array<string, mixed>> */
    public function loadAnalyticsRecords(): array
    {
        try {
            $analytics = Cloudflare::analytics()->getGraphQLAnalytics($this->days);
            $data = $analytics['viewer']['zones'][0]['zones'] ?? [];

            return collect($data)->map(function ($item, $index) {
                return [
                    'id' => (string) ($index + 1),
                    'datetime' => $item['dimensions']['timeslot'] ?? null,
                    'requests' => $item['sum']['requests'] ?? 0,
                    'cached_requests' => $item['sum']['cachedRequests'] ?? 0,
                    'bytes' => $item['sum']['bytes'] ?? 0,
                ];
            })->keyBy('id')->toArray();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error loading analytics')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return [];
        }
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CloudflareAnalyticsResource\Widgets\AnalyticsOverview::class,
            CloudflareAnalyticsResource\Widgets\UniqueVisitorsChart::class,
            CloudflareAnalyticsResource\Widgets\RequestsChart::class,
            CloudflareAnalyticsResource\Widgets\CachedRequestsPercentChart::class,
            CloudflareAnalyticsResource\Widgets\BytesChart::class,
            CloudflareAnalyticsResource\Widgets\CachedBytesChart::class,
            CloudflareAnalyticsResource\Widgets\ThreatsChart::class,
        ];
    }
}
