<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

use App\Models\User;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class NewUsersChart extends ChartWidget
{
    protected static ?string $heading = 'New Users';

    protected static ?string $pollingInterval = '100s';

    protected static string $color = 'success';

    public ?string $filter = 'week';

    protected function getData(): array
    {
        $start = match ($this->filter) {
            'week'      =>      now()->subDays(7),
            'month'     =>      now()->startOfMonth(),
            'year'      =>      now()->startOfYear(),
            default     =>      now()->startOfWeek()
        };

        $data = Trend::model(User::class)
            ->between(
                start: $start,
                end: now(),
            )
            ->perDay()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'New Users',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => $value->date),
        ];
    }

    protected function getFilters(): ?array {
        return [
            'week' => 'This week',
            'month' => 'This month',
            'year' => 'This year',
        ];
    }

    protected function getType(): string {
        return 'line';
    }
}
