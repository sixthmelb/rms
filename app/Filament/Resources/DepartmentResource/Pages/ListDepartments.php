<?php

namespace App\Filament\Resources\DepartmentResource\Pages;

use App\Filament\Resources\DepartmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDepartments extends ListRecords
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Department')
                ->icon('heroicon-o-plus')
                ->color('primary'),
        ];
    }

    public function getTabs(): array
    {
        $user = auth()->user();
        
        $tabs = [
            'all' => Tab::make('All Departments')
                ->badge(DepartmentResource::getEloquentQuery()->count()),
        ];

        // Add company-specific tab if user is not admin
        if (!$user->can('view_all_requests') && $user->company_id) {
            $tabs['my_company'] = Tab::make('My Company')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('company_id', $user->company_id))
                ->badge(DepartmentResource::getEloquentQuery()->where('company_id', $user->company_id)->count());
        }

        $tabs['with_section_head'] = Tab::make('With Section Head')
            ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('users', function ($q) {
                $q->role('section_head');
            }))
            ->badge(DepartmentResource::getEloquentQuery()->whereHas('users', function ($q) {
                $q->role('section_head');
            })->count());

        $tabs['no_section_head'] = Tab::make('No Section Head')
            ->modifyQueryUsing(fn (Builder $query) => $query->whereDoesntHave('users', function ($q) {
                $q->role('section_head');
            }))
            ->badge(DepartmentResource::getEloquentQuery()->whereDoesntHave('users', function ($q) {
                $q->role('section_head');
            })->count())
            ->badgeColor('warning');

        return $tabs;
    }
}