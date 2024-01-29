<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

use \App\Models\Record;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class NewRecordsChart extends ChartWidget
{
    protected static ?string $heading = 'New Records';

    protected static ?string $pollingInterval = '60s';

    protected static string $color = 'success';

    public ?string $filter = 'today';

    protected function getData(): array
    {
        $start = match ($this->filter) {
            'today'     =>      now()->startOfDay(),
            'week'      =>      now()->startOfWeek(),
            'month'     =>      now()->startOfMonth(),
            'year'      =>      now()->startOfYear(),
            default     =>      now()->startOfWeek()
        };

        $data = Trend::model(Record::class)
            ->between(
                start: $start,
                end: now(),
            )
            ->perHour()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'New Records',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => $value->date),
        ];
    }

    protected function getFilters(): ?array {
        return [
            'today' =>  'Today',
            'week'  => 'This week',
            'month' => 'This month',
            'year'  => 'This year',
        ];
    }

    protected function getType(): string {
        return 'line';
    }
}
