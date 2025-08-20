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
        
        // Auto-fill company_id if not set
        if (empty($data['company_id'])) {
            $data['company_id'] = auth()->user()->company_id;
        }
        
        // Auto-fill department_id if not set
        if (empty($data['department_id'])) {
            $data['department_id'] = auth()->user()->department_id;
        }
        
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