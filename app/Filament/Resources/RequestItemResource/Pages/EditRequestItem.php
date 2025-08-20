<?php

namespace App\Filament\Resources\RequestItemResource\Pages;

use App\Filament\Resources\RequestItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRequestItem extends EditRecord
{
    protected static string $resource = RequestItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
