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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Approval Details')
                    ->schema([
                        Forms\Components\Select::make('request_id')
                            ->relationship('request', 'request_number')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn ($context) => $context === 'edit'),

                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn ($context) => $context === 'edit'),

                        Forms\Components\Select::make('role')
                            ->options([
                                'section_head' => 'Section Head',
                                'scm_head' => 'SCM Head',
                                'pjo' => 'PJO',
                            ])
                            ->required()
                            ->disabled(fn ($context) => $context === 'edit'),

                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->required()
                            ->default('pending'),

                        Forms\Components\Textarea::make('comments')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Approval Information')
                    ->schema([
                        Forms\Components\DateTimePicker::make('approved_at')
                            ->displayFormat('d/m/Y H:i')
                            ->disabled(),

                        Forms\Components\TextInput::make('qr_code_data')
                            ->label('QR Code Data')
                            ->disabled(),
                    ])
                    ->columns(2)
                    ->visible(fn ($context) => $context === 'view' || $context === 'edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

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
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['request', 'user']);
        $user = auth()->user();

        // Admin bisa lihat semua
        if ($user->can('view_all_requests')) {
            return $query;
        }

        // Filter berdasarkan role approval yang bisa diakses user
        return $query->where(function ($q) use ($user) {
            if ($user->can('approve_section_requests')) {
                $q->orWhere(function ($subQ) use ($user) {
                    $subQ->where('role', 'section_head')
                         ->whereHas('request', function ($requestQ) use ($user) {
                             $requestQ->where('department_id', $user->department_id);
                         });
                });
            }

            if ($user->can('approve_scm_requests')) {
                $q->orWhere('role', 'scm_head');
            }

            if ($user->can('approve_final_requests')) {
                $q->orWhere('role', 'pjo');
            }

            // User juga bisa lihat approval untuk request mereka sendiri
            $q->orWhereHas('request', function ($requestQ) use ($user) {
                $requestQ->where('user_id', $user->id);
            });
        });
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

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        $count = 0;

        if ($user->can('approve_section_requests')) {
            $count += static::getEloquentQuery()
                ->where('role', 'section_head')
                ->where('status', 'pending')
                ->count();
        }

        if ($user->can('approve_scm_requests')) {
            $count += static::getEloquentQuery()
                ->where('role', 'scm_head')
                ->where('status', 'pending')
                ->count();
        }

        if ($user->can('approve_final_requests')) {
            $count += static::getEloquentQuery()
                ->where('role', 'pjo')
                ->where('status', 'pending')
                ->count();
        }

        return $count > 0 ? (string) $count : null;
    }
}