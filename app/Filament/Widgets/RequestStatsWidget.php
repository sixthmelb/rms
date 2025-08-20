<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Request;

class RequestStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        $stats = [];

        // My Requests Stats
        if ($user->can('manage_own_requests')) {
            $myRequests = $user->requests();
            
            $stats[] = Stat::make('My Requests', $myRequests->count())
                ->description('Total requests created')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary');

            $stats[] = Stat::make('Draft Requests', $myRequests->where('status', 'draft')->count())
                ->description('Pending submission')
                ->descriptionIcon('heroicon-m-pencil')
                ->color('warning');

            $stats[] = Stat::make('Completed', 
                $myRequests->where('status', 'completed')->count()
            )
                ->description('Approved requests')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success');
        }

        // Admin/PJO Stats
        if ($user->can('view_all_requests')) {
            $stats[] = Stat::make('Total Requests', Request::count())
                ->description('All time requests')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info');

            $stats[] = Stat::make('Pending Approval', 
                Request::whereIn('status', ['submitted', 'section_approved', 'scm_approved'])->count()
            )
                ->description('Awaiting approval')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning');

            $stats[] = Stat::make('This Month', 
                Request::whereMonth('created_at', now()->month)->count()
            )
                ->description('New requests this month')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary');
        }

        return $stats;
    }

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->can('manage_own_requests');
    }
}