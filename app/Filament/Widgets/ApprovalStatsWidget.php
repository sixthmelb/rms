<?php
// app/Filament/Widgets/ApprovalStatsWidget.php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Approval;

class ApprovalStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected static ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        $user = auth()->user();
        $stats = [];

        // Section Head Approvals
        if ($user->can('approve_section_requests')) {
            $sectionPending = Approval::where('role', 'section_head')
                ->where('status', 'pending')
                ->whereHas('request', function ($q) use ($user) {
                    $q->where('department_id', $user->department_id)
                      ->where('company_id', $user->company_id);
                })
                ->count();

            $stats[] = Stat::make('Section Approvals', $sectionPending)
                ->description($sectionPending > 0 ? 'Awaiting your approval' : 'All processed')
                ->descriptionIcon('heroicon-m-clock')
                ->color($sectionPending > 0 ? 'danger' : 'success')
                ->url('/admin/approvals?tableFilters[role][value]=section_head&tableFilters[status][value]=pending');
        }

        // SCM Head Approvals
        if ($user->can('approve_scm_requests')) {
            $scmPending = Approval::where('role', 'scm_head')
                ->where('status', 'pending')
                ->count();

            $stats[] = Stat::make('SCM Approvals', $scmPending)
                ->description($scmPending > 0 ? 'Central SCM approval needed' : 'All processed')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color($scmPending > 0 ? 'danger' : 'success')
                ->url('/admin/approvals?tableFilters[role][value]=scm_head&tableFilters[status][value]=pending');
        }

        // PJO Final Approvals
        if ($user->can('approve_final_requests')) {
            $pjoPending = Approval::where('role', 'pjo')
                ->where('status', 'pending')
                ->whereHas('request', function ($q) use ($user) {
                    $q->where('company_id', $user->company_id);
                })
                ->count();

            $stats[] = Stat::make('Final Approvals', $pjoPending)
                ->description($pjoPending > 0 ? 'PJO approval required' : 'All completed')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($pjoPending > 0 ? 'danger' : 'success')
                ->url('/admin/approvals?tableFilters[role][value]=pjo&tableFilters[status][value]=pending');
        }

        // Additional stats for all approvers
        if ($user->hasAnyRole(['section_head', 'scm_head', 'pjo'])) {
            $myApprovals = $user->approvals();
            
            $todayApprovals = $myApprovals->whereDate('approved_at', today())->count();
            $stats[] = Stat::make('Today Approved', $todayApprovals)
                ->description('Approvals completed today')
                ->descriptionIcon('heroicon-m-hand-thumb-up')
                ->color('primary');

            $totalApprovals = $myApprovals->where('status', 'approved')->count();
            $stats[] = Stat::make('Total Approved', $totalApprovals)
                ->description('Lifetime approvals')
                ->descriptionIcon('heroicon-m-chart-bar-square')
                ->color('info');
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