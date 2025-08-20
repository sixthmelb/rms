<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->id !== auth()->id()),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Set email_verified state for the toggle
        $data['email_verified'] = $this->record->email_verified_at !== null;
        
        // Load current roles
        $data['roles'] = $this->record->roles->pluck('id')->toArray();
        
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Handle email verification toggle
        if (isset($data['email_verified'])) {
            if ($data['email_verified'] && !$this->record->email_verified_at) {
                $data['email_verified_at'] = now();
            } elseif (!$data['email_verified']) {
                $data['email_verified_at'] = null;
            }
        }

        // Remove fields that are not database columns
        unset($data['email_verified'], $data['roles']);

        return $data;
    }

    protected function afterSave(): void
    {
        // Handle role updates after user save
        $formData = $this->form->getState();
        
        // Sync roles - remove all existing and add new ones
        $this->record->roles()->detach();
        
        if (isset($formData['roles']) && !empty($formData['roles'])) {
            $roleIds = collect($formData['roles']);
            $roles = \Spatie\Permission\Models\Role::whereIn('id', $roleIds)->get();
            
            foreach ($roles as $role) {
                $this->record->assignRole($role->name);
            }
        }
    }
}