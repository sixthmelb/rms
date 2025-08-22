<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApprovalResource\Pages;
use App\Models\Approval;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Section;

class ApprovalResource extends Resource
{
    protected static ?string $model = Approval::class;
    protected static ?string $navigationIcon = 'heroicon-o-check-circle';
    protected static ?string $navigationLabel = 'Approvals';
    protected static ?string $navigationGroup = 'Workflow Management';
    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()->can('approve_section_requests') ||
               auth()->user()->can('approve_scm_requests') ||
               auth()->user()->can('approve_final_requests') ||
               auth()->user()->can('view_all_requests');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                Approval::query()
                    ->with(['request', 'user'])
                    ->when(
                        !auth()->user()->can('view_all_requests'),
                        fn (Builder $query) => $query->where('user_id', auth()->id())
                    )
            )
            ->columns([
                Tables\Columns\TextColumn::make('request.request_number')
                    ->label('Request Number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('request.user.name')
                    ->label('Requester')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Approver')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('role')
                    ->colors([
                        'primary' => 'section_head',
                        'warning' => 'scm_head',
                        'success' => 'pjo',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'section_head' => 'Section Head',
                        'scm_head' => 'SCM Head',
                        'pjo' => 'PJO',
                        default => $state,
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'gray' => 'cancelled',                    // ✅ NEW
                        'orange' => 'revision_requested',        // ✅ NEW
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',              // ✅ NEW
                        'revision_requested' => 'Revision Requested', // ✅ NEW
                        default => $state,
                    }),

                Tables\Columns\BadgeColumn::make('request.status')
                    ->label('Request Status')
                    ->colors([
                        'secondary' => 'draft',
                        'warning' => 'submitted',
                        'info' => 'section_approved',
                        'primary' => 'scm_approved',
                        'success' => 'completed',
                        'danger' => 'rejected',
                        'gray' => 'cancelled',                    // ✅ NEW
                        'orange' => 'revision_requested',        // ✅ NEW
                    ]),

                Tables\Columns\TextColumn::make('approved_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Not yet approved'),

                Tables\Columns\TextColumn::make('comments')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),

                Tables\Columns\IconColumn::make('qr_code_path')
                    ->label('QR Code')
                    ->boolean()
                    ->trueIcon('heroicon-o-qr-code')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',                    // ✅ NEW
                        'revision_requested' => 'Revision Requested', // ✅ NEW
                    ]),

                SelectFilter::make('role')
                    ->options([
                        'section_head' => 'Section Head',
                        'scm_head' => 'SCM Head',
                        'pjo' => 'PJO',
                    ]),

                SelectFilter::make('request.status')
                    ->label('Request Status')
                    ->relationship('request', 'status')
                    ->options([
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'section_approved' => 'Section Approved',
                        'scm_approved' => 'SCM Approved',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',                    // ✅ NEW
                        'revision_requested' => 'Revision Requested', // ✅ NEW
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                // ✅ APPROVE ACTION
                Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Approval $record): bool => 
                        $record->status === 'pending' && 
                        $record->canBeApprovedBy(auth()->user())
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Approve Request')
                    ->modalDescription('Are you sure you want to approve this request?')
                    ->form([
                        Forms\Components\Textarea::make('comments')
                            ->label('Approval Comments')
                            ->placeholder('Enter your approval comments...')
                            ->rows(3),
                    ])
                    ->action(function (Approval $record, array $data) {
                        $record->approve($data['comments'] ?? null);
                        
                        Notification::make()
                            ->title('Request approved successfully')
                            ->success()
                            ->send();
                    }),

                // ✅ REJECT ACTION
                Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Approval $record): bool => 
                        $record->status === 'pending' && 
                        $record->canBeApprovedBy(auth()->user())
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Reject Request')
                    ->modalDescription('Are you sure you want to reject this request?')
                    ->form([
                        Forms\Components\Textarea::make('comments')
                            ->label('Rejection Reason')
                            ->placeholder('Enter reason for rejection...')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Approval $record, array $data) {
                        $record->reject($data['comments']);
                        
                        Notification::make()
                            ->title('Request rejected')
                            ->danger()
                            ->send();
                    }),

                // ✅ NEW: REQUEST REVISION ACTION
                Action::make('requestRevision')
                    ->label('Request Revision')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Approval $record): bool => 
                        $record->status === 'pending' && 
                        $record->canBeApprovedBy(auth()->user())
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Request Revision')
                    ->modalDescription('This will send the request back to the requester for revision.')
                    ->form([
                        Forms\Components\Textarea::make('revision_reason')
                            ->label('Revision Reason')
                            ->placeholder('Please explain what needs to be revised...')
                            ->required()
                            ->rows(4),
                    ])
                    ->action(function (Approval $record, array $data) {
                        $success = $record->request->requestRevision(auth()->user(), $data['revision_reason']);
                        
                        if ($success) {
                            Notification::make()
                                ->title('Revision requested successfully')
                                ->warning()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to request revision')
                                ->danger()
                                ->send();
                        }
                    }),

                // ✅ VIEW QR CODE ACTION
                Action::make('viewQrCode')
                    ->label('View QR')
                    ->icon('heroicon-o-qr-code')
                    ->color('primary')
                    ->visible(fn (Approval $record): bool => !empty($record->qr_code_path))
                    ->modalContent(fn (Approval $record) => view('filament.qr-code-modal', ['approval' => $record]))
                    ->modalHeading('Digital Signature QR Code'),

                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => auth()->user()->can('manage_system')),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn (): bool => auth()->user()->can('manage_system')),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApprovals::route('/'),
            'create' => Pages\CreateApproval::route('/create'),
            'view' => Pages\ViewApproval::route('/{record}'),
            'edit' => Pages\EditApproval::route('/{record}/edit'),
        ];
    }
}