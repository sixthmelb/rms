<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Request;
use App\Models\User;

class RequestStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $user = auth()->user();
        
        if (!$user) {
            return [];
        }

        $stats = [];

        try {
            // Personal Stats (Always shown for authenticated users)
            if ($user->can('manage_own_requests')) {
                $stats = array_merge($stats, $this->getPersonalStats($user));
            }

            // Management Stats (Additional for privileged users)
            if ($user->can('view_all_requests')) {
                $stats = array_merge($stats, $this->getSystemStats());
            }

            // Role-specific stats
            if ($user->hasAnyRole(['section_head', 'pjo', 'scm_head'])) {
                $stats = array_merge($stats, $this->getRoleSpecificStats($user));
            }

        } catch (\Exception $e) {
            \Log::error('RequestStatsWidget error: ' . $e->getMessage());
            
            // Fallback stats
            $stats[] = Stat::make('Error', 'Unable to load stats')
                ->description('Please refresh the page')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger');
        }

        return $stats;
    }

    private function getPersonalStats(User $user): array
    {
        $stats = [];

        try {
            // ✅ Safe query using where clause instead of relationship
            $myRequestsCount = Request::where('user_id', $user->id)->count();
            
            $stats[] = Stat::make('My Requests', $myRequestsCount)
                ->description('Total requests created')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary')
                ->url('/admin/requests?tableFilters[user][value]=' . $user->id);

            $draftCount = Request::where('user_id', $user->id)
                ->where('status', 'draft')
                ->count();
                
            $stats[] = Stat::make('Draft Requests', $draftCount)
                ->description($draftCount > 0 ? 'Need submission' : 'All submitted')
                ->descriptionIcon('heroicon-m-pencil')
                ->color($draftCount > 0 ? 'warning' : 'success')
                ->url('/admin/requests?tableFilters[status][value]=draft');

            $completedCount = Request::where('user_id', $user->id)
                ->where('status', 'completed')
                ->count();
                
            $stats[] = Stat::make('Completed', $completedCount)
                ->description('Approved requests')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->url('/admin/requests?tableFilters[status][value]=completed');

        } catch (\Exception $e) {
            \Log::error('Personal stats error: ' . $e->getMessage());
        }

        return $stats;
    }

    private function getSystemStats(): array
    {
        $stats = [];

        try {
            $totalRequests = Request::count();
            $stats[] = Stat::make('Total System Requests', $totalRequests)
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

        } catch (\Exception $e) {
            \Log::error('System stats error: ' . $e->getMessage());
        }

        return $stats;
    }

    private function getRoleSpecificStats(User $user): array
    {
        $stats = [];

        try {
            // Section Head stats
            if ($user->hasRole('section_head') && $user->department_id) {
                $deptRequests = Request::where('department_id', $user->department_id)->count();
                
                $stats[] = Stat::make('Department Requests', $deptRequests)
                    ->description($user->department?->name ?? 'Department total')
                    ->descriptionIcon('heroicon-m-building-office-2')
                    ->color('info')
                    ->url('/admin/requests?tableFilters[department][value]=' . $user->department_id);

                $pendingApproval = Request::where('department_id', $user->department_id)
                    ->where('status', 'submitted')
                    ->count();
                    
                $stats[] = Stat::make('Awaiting My Approval', $pendingApproval)
                    ->description($pendingApproval > 0 ? 'Action required' : 'All approved')
                    ->descriptionIcon('heroicon-m-clock')
                    ->color($pendingApproval > 0 ? 'warning' : 'success')
                    ->url('/admin/approvals');
            }

            // PJO stats
            if ($user->hasRole('pjo') && $user->company_id) {
                $companyRequests = Request::where('company_id', $user->company_id)->count();
                
                $stats[] = Stat::make('Company Requests', $companyRequests)
                    ->description($user->company?->name ?? 'Company total')
                    ->descriptionIcon('heroicon-m-building-office')
                    ->color('info')
                    ->url('/admin/requests?tableFilters[company][value]=' . $user->company_id);

                $awaitingFinalApproval = Request::where('company_id', $user->company_id)
                    ->where('status', 'scm_approved')
                    ->count();
                    
                $stats[] = Stat::make('Final Approval Needed', $awaitingFinalApproval)
                    ->description($awaitingFinalApproval > 0 ? 'Action required' : 'All processed')
                    ->descriptionIcon('heroicon-m-check-badge')
                    ->color($awaitingFinalApproval > 0 ? 'warning' : 'success')
                    ->url('/admin/approvals');
            }

            // SCM Head stats
            if ($user->hasRole('scm_head')) {
                $awaitingSCM = Request::where('status', 'section_approved')->count();
                
                $stats[] = Stat::make('SCM Approval Needed', $awaitingSCM)
                    ->description($awaitingSCM > 0 ? 'Action required' : 'All processed')
                    ->descriptionIcon('heroicon-m-cog-6-tooth')
                    ->color($awaitingSCM > 0 ? 'warning' : 'success')
                    ->url('/admin/approvals');
            }

        } catch (\Exception $e) {
            \Log::error('Role-specific stats error: ' . $e->getMessage());
        }

        return $stats;
    }

    public static function canView(): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }

        // Safe permission check
        try {
            return $user->can('manage_own_requests') || 
                   $user->can('view_all_requests') ||
                   $user->hasAnyRole(['section_head', 'pjo', 'scm_head']);
        } catch (\Exception $e) {
            \Log::error('RequestStatsWidget canView error: ' . $e->getMessage());
            return false;
        }
    }
}