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

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('request_number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Requester')
                    ->searchable(),
                    
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'draft',
                        'warning' => 'submitted',
                        'info' => 'section_approved',
                        'primary' => 'scm_approved',
                        'success' => 'completed',
                        'danger' => 'rejected',
                    ]),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->url(fn (Request $record): string => "/admin/requests/{$record->id}")
                    ->icon('heroicon-m-eye'),
            ])
            ->heading($this->getWidgetHeading())
            ->paginated(false);
    }

    // FIXED METHOD SIGNATURE
    protected function getTableQuery(): Builder
    {
        $user = auth()->user();
        
        if ($user->can('view_all_requests')) {
            return Request::query()
                ->with(['user'])
                ->latest()
                ->limit(10);
        }
        
        return $user->requests()
            ->with(['user'])
            ->latest()
            ->limit(10);
    }

    protected function getWidgetHeading(): string
    {
        return auth()->user()->can('view_all_requests') 
            ? 'Recent Requests' 
            : 'My Recent Requests';
    }

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->can('manage_own_requests');
    }
}