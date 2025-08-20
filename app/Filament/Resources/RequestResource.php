<?php
// app/Filament/Resources/RequestResource.php
namespace App\Filament\Resources;

use App\Filament\Resources\RequestResource\Pages;
use App\Models\Request;
use App\Models\Department;
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

class RequestResource extends Resource
{
    protected static ?string $model = Request::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Request Lists';
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return true;
        //auth()->user()->can('manage_own_requests') || 
         //   auth()->user()->can('view_all_requests');
    }

    public static function canCreate(): bool
    {
        return true;
        //auth()->user()->can('manage_own_requests');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                    Forms\Components\Section::make('Request Information')
                        ->schema([
                            Forms\Components\Grid::make(3)
                                ->schema([
                                    Forms\Components\TextInput::make('request_number')
                                        ->label('Request Number')
                                        ->disabled()
                                        ->placeholder('Auto Generated')
                                        ->dehydrated(false),
                                        
                                    Forms\Components\DatePicker::make('request_date')
                                        ->label('Request Date')
                                        ->required()
                                        ->default(now())
                                        ->disabled()
                                        ->displayFormat('d/m/Y')
                                        ->dehydrated(true),
                                        
                                    Forms\Components\Select::make('department_id')
                                        ->label('Department')
                                        ->relationship('department', 'name')
                                        ->required()
                                        ->default(fn() => auth()->user()?->department_id)
                                        ->disabled()
                                        ->preload()
                                        ->dehydrated(true)
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
                                            ->columnSpan(2)
                                            ->default(fn($get) => count($get('../../items')) + 0),

                                        Forms\Components\TextInput::make('description')
                                            ->required()
                                            ->columnSpan(2),
                                        Forms\Components\Textarea::make('specification')
                                            ->required()
                                            ->rows(1)
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('quantity')
                                            ->numeric()
                                            ->required()
                                            ->columnSpan(2),
                                    ]),
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('unit_of_measurement')
                                            ->required()
                                            ->placeholder('Unit, Pcs, Kg, etc'),
                                        Forms\Components\Textarea::make('remarks')
                                            ->rows(1),
                                    ]),
                            ])
                            ->minItems(1)
                            ->addActionLabel('Add Item')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['description'] ?? null)
                            ->columnSpanFull()
                           
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('request_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('request_date')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Requested By')
                    ->searchable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'draft',
                        'warning' => 'submitted',
                        'info' => 'section_approved',
                        'primary' => 'scm_approved',
                        'success' => 'completed',
                        'danger' => 'rejected',
                    ]),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'section_approved' => 'Section Approved',
                        'scm_approved' => 'SCM Approved',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\SelectFilter::make('department')
                    ->relationship('department', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Request $record): bool => $record->canBeEditedBy(auth()->user())),
                Action::make('submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (Request $record): bool => $record->status === 'draft' && $record->user_id === auth()->id())
                    ->requiresConfirmation()
                    ->action(function (Request $record) {
                        $record->update(['status' => 'submitted']);
                        static::createApprovalRecords($record);
                        
                        Notification::make()
                            ->title('Request submitted successfully')
                            ->success()
                            ->send();
                    }),
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Request $record): bool => $record->canBeApprovedBy(auth()->user()))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('comments')
                            ->label('Approval Comments'),
                    ])
                    ->action(function (Request $record, array $data) {
                        static::processApproval($record, $data['comments'] ?? null);
                    }),
                Action::make('downloadPdf')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->url(fn (Request $record): string => route('request.pdf', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn (): bool => auth()->user()->can('delete_request')),
            ]);
    }

    protected static function createApprovalRecords(Request $request): void
    {
        $approvalRoles = [
            ['role' => 'section_head', 'department_specific' => true],
            ['role' => 'scm_head', 'department_specific' => false],
            ['role' => 'pjo', 'department_specific' => false],
        ];

        foreach ($approvalRoles as $approval) {
            $query = \App\Models\User::role($approval['role']);
            
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
        $userRoles = auth()->user()->getRoleNames()->toArray();
        
        $currentRole = match ($request->status) {
            'submitted' => 'section_head',
            'section_approved' => 'scm_head',
            'scm_approved' => 'pjo',
            default => null
        };

        if ($currentRole && in_array($currentRole, $userRoles)) {
            $approval = $request->approvals()
                ->where('user_id', auth()->id())
                ->where('role', $currentRole)
                ->where('status', 'pending')
                ->first();

            if ($approval) {
                $approval->approve($comments);
                
                Notification::make()
                    ->title('Request approved successfully')
                    ->success()
                    ->send();
            }
        }
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        // Admin can see all
        if ($user->hasRole('admin')) {
            return $query;
        }

        // Users can only see their own requests
        if ($user->hasRole('user')) {
            return $query->where('user_id', $user->id);
        }

        // Approvers see requests based on workflow
        if ($user->hasRole('section_head')) {
            return $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere(function ($subQ) use ($user) {
                      $subQ->where('status', 'submitted')
                           ->where('department_id', $user->department_id);
                  });
            });
        }

        if ($user->hasRole('scm_head')) {
            return $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('status', 'section_approved');
            });
        }

        if ($user->hasRole('pjo')) {
            return $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('status', 'scm_approved');
            });
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRequests::route('/'),
            'create' => Pages\CreateRequest::route('/create'),
            //'view' => Pages\ViewRequest::route('/{record}'),
            'edit' => Pages\EditRequest::route('/{record}/edit'),
        ];
    }
}