<?php

namespace App\Filament\Resources\RequestResource\Pages;

use App\Filament\Resources\RequestResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListRequests extends ListRecords
{
    protected static string $resource = RequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Request')
                ->icon('heroicon-o-plus'),
        ];
    }
}