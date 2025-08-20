<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Approval;

class ApprovalStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        $stats = [];

        if ($user->can('approve_section_requests')) {
            $pending = Approval::where('role', 'section_head')
                ->where('status', 'pending')
                ->whereHas('request', function ($q) use ($user) {
                    $q->where('department_id', $user->department_id);
                })
                ->count();

            $stats[] = Stat::make('Section Approvals Pending', $pending)
                ->description('Awaiting your approval')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pending > 0 ? 'danger' : 'success')
                ->url('/admin/approvals?tableFilters[role][value]=section_head&tableFilters[status][value]=pending');
        }

        if ($user->can('approve_scm_requests')) {
            $pending = Approval::where('role', 'scm_head')
                ->where('status', 'pending')
                ->count();

            $stats[] = Stat::make('SCM Approvals Pending', $pending)
                ->description('Awaiting your approval')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pending > 0 ? 'danger' : 'success')
                ->url('/admin/approvals?tableFilters[role][value]=scm_head&tableFilters[status][value]=pending');
        }

        if ($user->can('approve_final_requests')) {
            $pending = Approval::where('role', 'pjo')
                ->where('status', 'pending')
                ->count();

            $stats[] = Stat::make('Final Approvals Pending', $pending)
                ->description('Awaiting your approval')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pending > 0 ? 'danger' : 'success')
                ->url('/admin/approvals?tableFilters[role][value]=pjo&tableFilters[status][value]=pending');
        }

        return $stats;
    }

    public static function canView(): bool
    {
        if (!auth()->check()) return false;
        
        $user = auth()->user();
        return $user->can('approve_section_requests') ||
               $user->can('approve_scm_requests') ||
               $user->can('approve_final_requests');
    }
}
