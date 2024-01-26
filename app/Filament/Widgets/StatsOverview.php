<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

use App\Models\User;
use App\Models\OnlinePlayer;
use App\Models\Server;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $users = User::where('created_at', '>=', now()->subDays(30))->count();
        $onlinePlayers = OnlinePlayer::count();

        $servers = Server::has('onlinePlayers')->count();

        return [
            Stat::make('New Users', $users)
                ->description('Past month')
                ->color('success'),

            Stat::make('Online Players', $onlinePlayers)
                ->description('In ' . $servers . ' Servers')
                ->color('info'),
        ];
    }
}
