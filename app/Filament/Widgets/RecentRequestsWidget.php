<?php
// app/Filament/Widgets/RecentRequestsWidget.php

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
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Small),
                    
                Tables\Columns\TextColumn::make('company.code')
                    ->label('Company')
                    ->badge()
                    ->color('primary')
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Small),
                    
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Requester')
                    ->searchable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Small),
                    
                Tables\Columns\BadgeColumn::make('status')
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
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Small),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m H:i')
                    ->sortable()
                    ->size(Tables\Columns\TextColumn\TextColumnSize::Small),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->url(fn (Request $record): string => "/admin/requests/{$record->id}")
                    ->icon('heroicon-m-eye')
                    ->size('sm'),
                    
                Tables\Actions\Action::make('pdf')
                    ->url(fn (Request $record): string => route('request.pdf', $record))
                    ->icon('heroicon-m-document-arrow-down')
                    ->openUrlInNewTab()
                    ->size('sm')
                    ->color('primary'),
            ])
            ->heading($this->getWidgetHeading())
            ->description('Latest requests in the system')
            ->paginated(false)
            ->defaultSort('created_at', 'desc');
    }

    // ✅ FIXED: Correct method signature for Filament v3
    protected function getTableQuery(): Builder
    {
        $user = auth()->user();
        
        if ($user->can('view_all_requests')) {
            return Request::query()
                ->with(['user', 'company', 'items'])
                ->latest()
                ->limit(8);
        }
        
        return $user->requests()
            ->with(['user', 'company', 'items'])
            ->latest()
            ->limit(8);
    }

    protected function getWidgetHeading(): string
    {
        return auth()->user()->can('view_all_requests') 
            ? 'Recent Requests (All Companies)' 
            : 'My Recent Requests';
    }

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->can('manage_own_requests');
    }
}