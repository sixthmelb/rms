<?php

namespace App\Filament\Resources\RequestResource\Pages;

use App\Filament\Resources\RequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRequest extends CreateRecord
{
    protected static string $resource = RequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-fill user_id
        $data['user_id'] = auth()->id();
        
        // Set default values
        if (empty($data['request_date'])) {
            $data['request_date'] = now()->toDateString();
        }
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}