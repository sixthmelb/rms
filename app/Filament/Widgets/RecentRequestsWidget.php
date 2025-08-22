<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Request;
use Illuminate\Database\Eloquent\Builder;

class RecentRequestsWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Recent Requests';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('request_number')
                    ->label('Request #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->badge()
                    ->color('primary')
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Small),
                    
                Tables\Columns\TextColumn::make('company.code')
                    ->label('Company')
                    ->badge()
                    ->color('info')
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Small),
                    
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Requester')
                    ->searchable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Small)
                    ->description(fn (Request $record): string => $record->user->employee_id ?? ''),
                    
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'secondary' => 'draft',
                        'warning' => 'submitted',
                        'info' => 'section_approved', 
                        'primary' => 'scm_approved',
                        'success' => 'completed',
                        'danger' => 'rejected',
                    ])
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Small),
                    
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items')
                    ->alignCenter()
                    ->badge()
                    ->color('secondary')
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Small),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d/m H:i')
                    ->sortable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Small)
                    ->description(fn (Request $record): string => $record->created_at->diffForHumans()),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->url(fn (Request $record): string => "/admin/requests/{$record->id}/view")
                    ->icon('heroicon-m-eye')
                    ->size('sm')
                    ->color('info'),
                    
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->url(fn (Request $record): string => route('request.pdf', $record))
                    ->icon('heroicon-m-document-arrow-down')
                    ->openUrlInNewTab()
                    ->size('sm')
                    ->color('primary')
                    ->visible(fn (Request $record): bool => $record->status !== 'draft'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create_request')
                    ->label('New Request')
                    ->url('/admin/requests/create')
                    ->icon('heroicon-m-plus')
                    ->color('primary'),
            ])
            ->heading($this->getWidgetHeading())
            ->description($this->getWidgetDescription())
            ->paginated(false)
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->emptyStateHeading('No Recent Requests')
            ->emptyStateDescription('Create your first request to get started.')
            ->emptyStateActions([
                Tables\Actions\Action::make('create_first_request')
                    ->label('Create Request')
                    ->url('/admin/requests/create')
                    ->icon('heroicon-m-plus')
                    ->color('primary'),
            ]);
    }

    // ✅ FIXED: Proper method signature returning Builder instance
    protected function getTableQuery(): Builder
    {
        $user = auth()->user();
        
        // Admin/Super users can see all requests
        if ($user->can('view_all_requests')) {
            return Request::query()
                ->with(['user', 'company', 'department', 'items'])
                ->latest()
                ->limit(10);
        }
        
        // Section heads can see department requests
        if ($user->hasRole('section_head') && $user->department_id) {
            return Request::query()
                ->where('department_id', $user->department_id)
                ->with(['user', 'company', 'department', 'items'])
                ->latest()
                ->limit(8);
        }
        
        // PJO can see company requests
        if ($user->hasRole('pjo') && $user->company_id) {
            return Request::query()
                ->where('company_id', $user->company_id)
                ->with(['user', 'company', 'department', 'items'])
                ->latest()
                ->limit(8);
        }
        
        // SCM heads can see requests pending their approval
        if ($user->hasRole('scm_head')) {
            return Request::query()
                ->whereIn('status', ['section_approved', 'scm_approved'])
                ->with(['user', 'company', 'department', 'items'])
                ->latest()
                ->limit(8);
        }
        
        // ✅ FIXED: Convert HasMany to Builder properly
        // Regular users see only their own requests
        return Request::query()
            ->where('user_id', $user->id)
            ->with(['user', 'company', 'department', 'items'])
            ->latest()
            ->limit(5);
    }

    protected function getWidgetHeading(): string
    {
        $user = auth()->user();
        
        if ($user->can('view_all_requests')) {
            return 'Recent Requests (All Companies)';
        }
        
        if ($user->hasRole('section_head')) {
            return 'Recent Department Requests';
        }
        
        if ($user->hasRole('pjo')) {
            return 'Recent Company Requests';
        }
        
        if ($user->hasRole('scm_head')) {
            return 'Requests Pending SCM Approval';
        }
        
        return 'My Recent Requests';
    }

    protected function getWidgetDescription(): string
    {
        $user = auth()->user();
        
        if ($user->can('view_all_requests')) {
            return 'Latest requests from all companies in the system';
        }
        
        if ($user->hasRole('section_head')) {
            return 'Latest requests from your department';
        }
        
        if ($user->hasRole('pjo')) {
            return 'Latest requests from your company';
        }
        
        if ($user->hasRole('scm_head')) {
            return 'Requests awaiting your approval';
        }
        
        return 'Your latest request submissions';
    }

    // ✅ Enhanced visibility control
    public static function canView(): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }
        
        // Check if user has any permission to manage requests
        return $user->can('manage_own_requests') || 
               $user->can('view_all_requests') ||
               $user->hasAnyRole(['section_head', 'pjo', 'scm_head']);
    }

    // ✅ Add polling for real-time updates
    protected static ?string $pollingInterval = '30s';

    // ✅ Make widget responsive
    public function getColumnSpan(): string | array | int
    {
        return [
            'md' => 2,
            'xl' => 3,
        ];
    }
}