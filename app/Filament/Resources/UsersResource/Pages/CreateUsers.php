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
        // Set email verified if not specified
        if (!isset($data['email_verified'])) {
            $data['email_verified_at'] = now();
        } elseif ($data['email_verified']) {
            $data['email_verified_at'] = now();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Send welcome email or other post-creation tasks
        // $this->record->notify(new WelcomeNotification());
    }
}