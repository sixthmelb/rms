<?php

namespace App\Filament\Resources\RequestResource\Pages;

use App\Filament\Resources\RequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

class EditRequest extends EditRecord
{
    protected static string $resource = RequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Show status-specific actions based on request status
            Actions\Action::make('resubmit')
                ->label('Save & Resubmit')
                ->icon('heroicon-o-arrow-up-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === 'revision_requested')
                ->requiresConfirmation()
                ->modalHeading('Save Changes and Resubmit Request')
                ->modalDescription('Your changes will be saved and the request will be resubmitted for approval.')
                ->action(function () {
                    // Save the form first
                    $this->save();
                    
                    // Then resubmit
                    $this->record->resubmitAfterRevision();
                    
                    Notification::make()
                        ->title('Request updated and resubmitted successfully')
                        ->success()
                        ->send();
                        
                    return redirect($this->getResource()::getUrl('index'));
                }),

            Actions\Action::make('cancel')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->record->canBeCancelledBy(auth()->user()))
                ->requiresConfirmation()
                ->modalHeading('Cancel Request')
                ->modalDescription('Are you sure you want to cancel this request? This action cannot be undone.')
                ->form([
                    \Filament\Forms\Components\Textarea::make('cancellation_reason')
                        ->label('Cancellation Reason')
                        ->placeholder('Please provide a reason for cancelling this request...')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $this->record->cancelRequest($data['cancellation_reason']);
                    
                    Notification::make()
                        ->title('Request cancelled successfully')
                        ->warning()
                        ->send();
                        
                    return redirect($this->getResource()::getUrl('index'));
                }),

            Actions\DeleteAction::make()
                ->visible(fn (): bool => $this->record->isDeletable()),
        ];
    }

    protected function beforeFill(): void
    {
        // Show alert if this is a revision request
        if ($this->record->status === 'revision_requested') {
            Notification::make()
                ->title('Revision Required')
                ->body('This request requires revision. Please make the necessary changes and resubmit.')
                ->warning()
                ->persistent()
                ->send();
        }
    }

    protected function beforeSave(): void
    {
        // Additional validation for revision requests
        if ($this->record->status === 'revision_requested') {
            // Log that the revision is being saved
            \Illuminate\Support\Facades\Log::info('Revision request being saved', [
                'request_id' => $this->record->id,
                'user_id' => auth()->id()
            ]);
        }
    }

    protected function afterSave(): void
    {
        // Show different success messages based on status
        if ($this->record->status === 'revision_requested') {
            Notification::make()
                ->title('Changes saved')
                ->body('Your revision has been saved. Click "Save & Resubmit" to submit for approval.')
                ->info()
                ->send();
        } else {
            Notification::make()
                ->title('Request updated successfully')
                ->success()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function getBreadcrumbs(): array
    {
        $breadcrumbs = parent::getBreadcrumbs();
        
        // Add status indicator to breadcrumb for revision requests
        if ($this->record->status === 'revision_requested') {
            $breadcrumbs[] = 'Revision Required';
        }
        
        return $breadcrumbs;
    }
}