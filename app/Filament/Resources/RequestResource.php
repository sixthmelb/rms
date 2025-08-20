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

class RequestResource extends Resource
{
    protected static ?string $model = Request::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Request Lists';
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
                                    ->dehydrated(false),
                                    
                                Forms\Components\DatePicker::make('request_date')
                                    ->label('Request Date')
                                    ->required()
                                    ->default(now())
                                    ->displayFormat('d/m/Y')
                                    ->disabled(fn ($context) => $context !== 'create'),
                                    
                                Forms\Components\Select::make('company_id')
                                    ->label('Company')
                                    ->relationship('company', 'name')
                                    ->required()
                                    ->default(fn() => auth()->user()?->company_id)
                                    ->disabled(fn ($context) => $context !== 'create')
                                    ->preload()
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('department_id', null)),
                                    
                                Forms\Components\Select::make('department_id')
                                    ->label('Department')
                                    ->options(function (Forms\Get $get) {
                                        $companyId = $get('company_id');
                                        if (!$companyId) return [];
                                        
                                        return Department::where('company_id', $companyId)
                                            ->pluck('name', 'id');
                                    })
                                    ->required()
                                    ->default(fn() => auth()->user()?->department_id)
                                    ->disabled(fn ($context) => $context !== 'create')
                                    ->searchable()
                                    ->preload(),
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
                                            
                                        Forms\Components\TextInput::make('unit_of_measurement')
                                            ->required()
                                            ->placeholder('Unit, Pcs, Kg, etc')
                                            ->columnSpan(2),
                                    ]),
                                    
                                Forms\Components\Textarea::make('remarks')
                                    ->rows(1)
                                    ->columnSpanFull(),
                            ])
                            ->minItems(1)
                            ->addActionLabel('Add Item')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['description'] ?? null)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('request_number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('company.code')
                    ->label('Company')
                    ->badge()
                    ->color('primary')
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
                Tables\Filters\SelectFilter::make('company')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),
                    
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
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Request $record): bool => $record->canBeEditedBy(auth()->user())),
                    
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
                    ->visible(fn (): bool => auth()->user()->can('manage_system')),
            ]);
    }

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
        
        $currentRole = match ($request->status) {
            'submitted' => 'section_head',
            'section_approved' => 'scm_head', 
            'scm_approved' => 'pjo',
            default => null
        };

        if ($currentRole && $user->hasRole($currentRole)) {
            $approvalQuery = $request->approvals()
                ->where('user_id', $user->id)
                ->where('role', $currentRole)
                ->where('status', 'pending');
                
            // For company-specific roles, add company check
            if (in_array($currentRole, ['section_head', 'pjo'])) {
                $approvalQuery->whereHas('request', function ($q) use ($user) {
                    $q->where('company_id', $user->company_id);
                });
            }
            
            $approval = $approvalQuery->first();

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

        // Super admin dapat melihat semua
        if ($user->hasRole('admin')) {
            return $query;
        }

        // Users hanya dapat melihat request mereka sendiri
        if ($user->hasRole('user') && !$user->hasAnyRole(['section_head', 'scm_head', 'pjo'])) {
            return $query->where('user_id', $user->id);
        }

        // Approvers melihat berdasarkan workflow dan company
        $companyQuery = function ($q) use ($user) {
            $q->where('user_id', $user->id); // Own requests
        };

        if ($user->hasRole('section_head')) {
            $companyQuery = function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere(function ($subQ) use ($user) {
                      $subQ->where('status', 'submitted')
                           ->where('company_id', $user->company_id)
                           ->where('department_id', $user->department_id);
                  });
            };
        }

        if ($user->hasRole('scm_head')) {
            $companyQuery = function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('status', 'section_approved'); // SCM can see all section-approved
            };
        }

        if ($user->hasRole('pjo')) {
            $companyQuery = function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere(function ($subQ) use ($user) {
                      $subQ->where('status', 'scm_approved')
                           ->where('company_id', $user->company_id);
                  });
            };
        }

        return $query->where($companyQuery);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRequests::route('/'),
            'create' => Pages\CreateRequest::route('/create'),
            'edit' => Pages\EditRequest::route('/{record}/edit'),
        ];
    }
}