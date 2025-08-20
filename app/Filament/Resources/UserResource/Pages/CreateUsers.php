<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('User created successfully')
            ->body('The new user has been created and can now access the system.');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set email verified if the toggle was checked
        if (isset($data['email_verified']) && $data['email_verified']) {
            $data['email_verified_at'] = now();
        }

        // Remove the roles field - we'll handle it separately
        unset($data['email_verified'], $data['roles']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Handle role assignment after user creation
        $formData = $this->form->getState();
        
        if (isset($formData['roles']) && !empty($formData['roles'])) {
            // Get role IDs and assign using Spatie method
            $roleIds = collect($formData['roles']);
            $roles = \Spatie\Permission\Models\Role::whereIn('id', $roleIds)->get();
            
            foreach ($roles as $role) {
                $this->record->assignRole($role->name);
            }
        }
    }
}