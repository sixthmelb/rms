<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RequestResource\Pages;
use App\Models\Request;
use App\Models\Department;
use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;

class RequestResource extends Resource
{
    protected static ?string $model = Request::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Requests';
    protected static ?string $navigationGroup = 'Workflow Management';
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        return true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Request Information')
                    ->schema([
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\TextInput::make('request_number')
                                    ->label('Request Number')
                                    ->disabled()
                                    ->placeholder('Auto Generated')
                                    ->dehydrated(false)
                                    ->columnSpan(2),
                                    
                                Forms\Components\DatePicker::make('request_date')
                                    ->label('Request Date')
                                    ->required()
                                    ->default(now())
                                    ->columnSpan(2),

                                Forms\Components\Select::make('company_id')
                                    ->label('Company')
                                    ->relationship('company', 'name')
                                    ->required()
                                    ->default(fn() => auth()->user()?->company_id)
                                    ->disabled(fn ($context) => $context !== 'create')
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(2)
                                    ->reactive()
                                    ->afterStateUpdated(fn (callable $set) => $set('department_id', null)),

                                Forms\Components\Select::make('department_id')
                                    ->label('Department')
                                    ->options(function (callable $get) {
                                        $companyId = $get('company_id');
                                        if (!$companyId) {
                                            return [];
                                        }
                                        return Department::where('company_id', $companyId)
                                            ->pluck('name', 'id');
                                    })
                                    ->required()
                                    ->default(fn() => auth()->user()?->department_id)
                                    ->disabled(fn ($context) => $context !== 'create')
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(2),
                            ]),
                            
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            
                Section::make('Request Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\Grid::make(8)
                                    ->schema([
                                        Forms\Components\TextInput::make('item_number')
                                            ->numeric()
                                            ->required()
                                            ->columnSpan(1)
                                            ->default(function ($get) {
                                                $items = $get('../../items') ?? [];
                                                return count($items) + 0;
                                            }),

                                        Forms\Components\TextInput::make('description')
                                            ->required()
                                            ->columnSpan(3),

                                        Forms\Components\Textarea::make('specification')
                                            ->required()
                                            ->rows(2)
                                            ->columnSpan(4),

                                        Forms\Components\TextInput::make('quantity')
                                            ->numeric()
                                            ->required()
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('unit_of_measurement')
                                            ->required()
                                            ->columnSpan(2),

                                        Forms\Components\Textarea::make('remarks')
                                            ->rows(2)
                                            ->columnSpan(6),
                                    ]),
                            ])
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['description'] ?? null)
                            ->addActionLabel('Add Item')
                            ->reorderableWithButtons()
                            ->cloneable()
                            ->minItems(1)
                            ->defaultItems(1),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(static::getEloquentQuery())
            ->columns([
                Tables\Columns\TextColumn::make('request_number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Requester')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('department.name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('company.code')
                    ->label('Company')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('request_date')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'draft',
                        'warning' => 'submitted',
                        'info' => 'section_approved',
                        'primary' => 'scm_approved',
                        'success' => 'completed',
                        'danger' => 'rejected',
                        'gray' => 'cancelled',
                        'orange' => 'revision_requested',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'section_approved' => 'Section Approved',
                        'scm_approved' => 'SCM Approved',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                        'revision_requested' => 'Revision Requested',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items'),
            ])
            ->filters([
                SelectFilter::make('company')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'section_approved' => 'Section Approved',
                        'scm_approved' => 'SCM Approved',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                        'revision_requested' => 'Revision Requested',
                    ]),

                SelectFilter::make('department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                
                Tables\Actions\EditAction::make()
                    ->visible(fn (Request $record): bool => $record->canBeEditedBy(auth()->user())),

                // SUBMIT ACTION
                Action::make('submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (Request $record): bool => 
                        $record->status === 'draft' && 
                        $record->user_id === auth()->id()
                    )
                    ->requiresConfirmation()
                    ->action(function (Request $record) {
                        $record->update(['status' => 'submitted']);
                        static::createApprovalRecords($record);
                        
                        Notification::make()
                            ->title('Request submitted successfully')
                            ->success()
                            ->send();
                    }),

                // APPROVE ACTION  
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Request $record): bool => $record->canBeApprovedBy(auth()->user()))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('comments')
                            ->label('Approval Comments')
                            ->placeholder('Enter your approval comments...')
                            ->rows(3),
                    ])
                    ->action(function (Request $record, array $data) {
                        static::processApproval($record, $data['comments'] ?? null);
                    }),

                // CANCEL ACTION
                Action::make('cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Request $record): bool => $record->canBeCancelledBy(auth()->user()))
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Request')
                    ->modalDescription('Are you sure you want to cancel this request? This action cannot be undone.')
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->placeholder('Please provide a reason for cancelling this request...')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Request $record, array $data) {
                        $record->cancelRequest($data['cancellation_reason']);
                        
                        Notification::make()
                            ->title('Request cancelled successfully')
                            ->warning()
                            ->send();
                    }),

                // REQUEST REVISION ACTION
                Action::make('requestRevision')
                    ->label('Request Revision')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Request $record): bool => $record->canRequestRevisionBy(auth()->user()))
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
                    ->action(function (Request $record, array $data) {
                        $record->requestRevision(auth()->user(), $data['revision_reason']);
                        
                        Notification::make()
                            ->title('Revision requested successfully')
                            ->warning()
                            ->send();
                    }),

                // RESUBMIT AFTER REVISION ACTION
                Action::make('resubmit')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('info')
                    ->visible(fn (Request $record): bool => 
                        $record->status === 'revision_requested' && 
                        $record->user_id === auth()->id()
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Resubmit Request')
                    ->modalDescription('Are you ready to resubmit this request after revision?')
                    ->action(function (Request $record) {
                        $record->resubmitAfterRevision();
                        
                        Notification::make()
                            ->title('Request resubmitted successfully')
                            ->success()
                            ->send();
                    }),

                // DOWNLOAD PDF ACTION
                Action::make('downloadPdf')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->url(fn (Request $record): string => route('request.pdf', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn (): bool => auth()->user()->can('manage_system')),
            ]);
    }

    // HELPER METHODS
    protected static function createApprovalRecords(Request $request): void
    {
        $approvalRoles = [
            ['role' => 'section_head', 'company_specific' => true, 'department_specific' => true],
            ['role' => 'scm_head', 'company_specific' => false, 'department_specific' => false],
            ['role' => 'pjo', 'company_specific' => true, 'department_specific' => false],
        ];

        foreach ($approvalRoles as $approval) {
            $query = \App\Models\User::role($approval['role']);
            
            if ($approval['company_specific']) {
                $query->where('company_id', $request->company_id);
            }
            
            if ($approval['department_specific']) {
                $query->where('department_id', $request->department_id);
            }
            
            $approvers = $query->get();
            
            foreach ($approvers as $approver) {
                \App\Models\Approval::create([
                    'request_id' => $request->id,
                    'user_id' => $approver->id,
                    'role' => $approval['role'],
                    'status' => 'pending',
                ]);
            }
        }
    }

    protected static function processApproval(Request $request, ?string $comments): void
    {
        $user = auth()->user();
        
        // Find the user's pending approval for this request
        $approval = $request->approvals()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$approval) {
            Notification::make()
                ->title('No pending approval found')
                ->danger()
                ->send();
            return;
        }

        $approval->approve($comments);
        
        Notification::make()
            ->title('Request approved successfully')
            ->success()
            ->send();
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery();

        // Admin sees all
        if ($user->hasRole('admin')) {
            return $query;
        }

        // Regular users see only their own requests
        if ($user->hasRole('user') && !$user->hasAnyRole(['section_head', 'scm_head', 'pjo'])) {
            return $query->where('user_id', $user->id);
        }

        // Build complex query for approvers
        return $query->where(function ($q) use ($user) {
            // Always include own requests
            $q->where('user_id', $user->id);

            // Section Head: see department requests that need section approval
            if ($user->hasRole('section_head')) {
                $q->orWhere(function ($subQ) use ($user) {
                    $subQ->where('status', 'submitted')
                         ->where('company_id', $user->company_id)
                         ->where('department_id', $user->department_id);
                });
            }

            // SCM Head: see all section-approved requests (centralized)
            if ($user->hasRole('scm_head')) {
                $q->orWhere('status', 'section_approved');
            }

            // PJO: see company requests that need final approval
            if ($user->hasRole('pjo')) {
                $q->orWhere(function ($subQ) use ($user) {
                    $subQ->where('status', 'scm_approved')
                         ->where('company_id', $user->company_id);
                });
            }
        });
    }

    public static function getRelations(): array
    {
        return [
            // Add relation managers here if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRequests::route('/'),
            'create' => Pages\CreateRequest::route('/create'),
            'view' => Pages\ViewRequest::route('/{record}'),
            'edit' => Pages\EditRequest::route('/{record}/edit'),
        ];
    }
}