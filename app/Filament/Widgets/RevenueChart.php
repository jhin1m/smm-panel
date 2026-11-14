<?php

namespace App\Filament\Widgets;

use App\Services\AdminMetrics;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    public function getHeading(): string
    {
        return __('admin.widgets.revenue_7d');
    }

    public function getDescription(): string
    {
        return __('admin.widgets.chart_desc');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $series = app(AdminMetrics::class)->revenueSeries();
        $days = __('admin.widgets.weekdays');

        return [
            'datasets' => [[
                'label' => __('admin.widgets.chart_dataset'),
                'data' => array_column($series, 'revenue'),
                'backgroundColor' => array_map(fn ($i) => $i === count($series) - 1 ? '#1f47e6' : '#e2e8ff', array_keys($series)),
                'borderRadius' => 6,
            ]],
            'labels' => array_map(fn ($r) => $days[CarbonImmutable::parse($r['day'])->dayOfWeek].' '.CarbonImmutable::parse($r['day'])->format('d/m'), $series),
        ];
    }
}
