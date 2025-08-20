<?php

namespace App\Filament\Resources\DepartmentResource\Pages;

use App\Filament\Resources\DepartmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewDepartment extends ViewRecord
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->color('primary'),
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->users()->count() === 0),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Department Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('company.name')
                            ->label('Company')
                            ->badge()
                            ->color('primary'),

                        Infolists\Components\TextEntry::make('name')
                            ->label('Department Name')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('code')
                            ->label('Department Code')
                            ->badge()
                            ->color('secondary'),
                    ])->columns(3),

                Infolists\Components\Section::make('Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('users_count')
                            ->label('Total Users')
                            ->getStateUsing(fn ($record) => $record->users()->count())
                            ->badge()
                            ->color('info'),

                        Infolists\Components\TextEntry::make('active_requests_count')
                            ->label('Active Requests')
                            ->getStateUsing(fn ($record) => $record->requests()->whereNotIn('status', ['completed', 'rejected'])->count())
                            ->badge()
                            ->color('warning'),

                        Infolists\Components\TextEntry::make('completed_requests_count')
                            ->label('Completed Requests')
                            ->getStateUsing(fn ($record) => $record->requests()->where('status', 'completed')->count())
                            ->badge()
                            ->color('success'),

                        Infolists\Components\TextEntry::make('section_head')
                            ->label('Section Head')
                            ->getStateUsing(fn ($record) => $record->getSectionHead()?->name ?? 'Not assigned')
                            ->badge()
                            ->color(fn ($record) => $record->getSectionHead() ? 'success' : 'danger'),
                    ])->columns(4),

                Infolists\Components\Section::make('Recent Activity')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('requests')
                            ->label('Recent Requests')
                            ->schema([
                                Infolists\Components\TextEntry::make('request_number')
                                    ->label('Request #'),
                                Infolists\Components\TextEntry::make('user.name')
                                    ->label('Requester'),
                                Infolists\Components\TextEntry::make('status')
                                    ->badge(),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->dateTime('d/m/Y H:i'),
                            ])
                            ->columns(4),
                    ])
                    ->collapsible(),
            ]);
    }
}
