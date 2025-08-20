<?php
// app/Filament/Widgets/RequestStatsWidget.php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Request;

class RequestStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected static ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        $user = auth()->user();
        $stats = [];

        // Personal Stats (Always shown)
        if ($user->can('manage_own_requests')) {
            $myRequests = $user->requests();
            
            $stats[] = Stat::make('My Requests', $myRequests->count())
                ->description('Total requests created')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary')
                ->url('/admin/requests?tableFilters[user][value]=' . $user->id);

            $draftCount = $myRequests->where('status', 'draft')->count();
            $stats[] = Stat::make('Draft Requests', $draftCount)
                ->description($draftCount > 0 ? 'Need submission' : 'All submitted')
                ->descriptionIcon('heroicon-m-pencil')
                ->color($draftCount > 0 ? 'warning' : 'success')
                ->url('/admin/requests?tableFilters[status][value]=draft');

            $completedCount = $myRequests->where('status', 'completed')->count();
            $stats[] = Stat::make('Completed', $completedCount)
                ->description('Approved requests')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->url('/admin/requests?tableFilters[status][value]=completed');
        }

        // Admin/Management Stats (Additional for privileged users)
        if ($user->can('view_all_requests')) {
            $stats[] = Stat::make('Total System Requests', Request::count())
                ->description('All companies combined')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info')
                ->url('/admin/requests');

            $pendingCount = Request::whereIn('status', ['submitted', 'section_approved', 'scm_approved'])->count();
            $stats[] = Stat::make('Pending Approval', $pendingCount)
                ->description($pendingCount > 0 ? 'Require attention' : 'All processed')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingCount > 0 ? 'danger' : 'success')
                ->url('/admin/approvals?tableFilters[status][value]=pending');

            $thisMonthCount = Request::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();
            $stats[] = Stat::make('This Month', $thisMonthCount)
                ->description('New requests in ' . now()->format('F Y'))
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary');
        }

        // Company-specific stats for managers
        if (!$user->can('view_all_requests') && $user->hasAnyRole(['section_head', 'pjo'])) {
            $companyRequests = Request::where('company_id', $user->company_id);
            
            $stats[] = Stat::make('Company Requests', $companyRequests->count())
                ->description($user->company->name ?? 'Company total')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('info')
                ->url('/admin/requests');
        }

        return $stats;
    }

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->can('manage_own_requests');
    }
}