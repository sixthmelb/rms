<?php
// app/Filament/Resources/DepartmentResource.php - FIXED VERSION

namespace App\Filament\Resources;

use App\Filament\Resources\DepartmentResource\Pages;
use App\Models\Department;
use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\Section;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Departments';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_system') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Department Information')
                    ->description('Basic department details and company assignment')
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label('Company')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                // Auto-generate department code suggestion
                                if ($state) {
                                    $company = Company::find($state);
                                    if ($company) {
                                        $deptCount = Department::where('company_id', $state)->count();
                                        $suggestedCode = 'DEPT' . str_pad($deptCount + 1, 3, '0', STR_PAD_LEFT);
                                        $set('code', $suggestedCode);
                                    }
                                }
                            })
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('name')
                            ->label('Department Name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                // Auto-generate code from name if not set
                                if ($state && !$get('code')) {
                                    $code = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $state), 0, 3));
                                    if (strlen($code) < 3) {
                                        $code = 'DEPT';
                                    }
                                    $set('code', $code);
                                }
                            })
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('code')
                            ->label('Department Code')
                            ->required()
                            ->maxLength(10)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->rules([
                                function (Forms\Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $companyId = $get('company_id');
                                        if ($companyId) {
                                            $exists = Department::where('code', $value)
                                                ->where('company_id', $companyId)
                                                ->when(request()->route('record'), function ($query) {
                                                    $query->where('id', '!=', request()->route('record'));
                                                })
                                                ->exists();
                                                
                                            if ($exists) {
                                                $fail('This department code already exists in the selected company.');
                                            }
                                        }
                                    };
                                },
                            ])
                            ->helperText('Unique code within the company')
                            ->columnSpan(1),
                    ])->columns(4),

                Section::make('Department Statistics')
                    ->description('Current department metrics and activity')
                    ->schema([
                        Forms\Components\Placeholder::make('users_count')
                            ->label('Total Users')
                            ->content(fn ($record): string => $record ? number_format($record->users()->count()) : '0')
                            ->visible(fn ($context) => $context === 'edit' || $context === 'view'),

                        Forms\Components\Placeholder::make('active_requests')
                            ->label('Active Requests')
                            ->content(fn ($record): string => $record ? 
                                number_format($record->requests()->whereNotIn('status', ['completed', 'rejected'])->count()) : '0')
                            ->visible(fn ($context) => $context === 'edit' || $context === 'view'),

                        Forms\Components\Placeholder::make('completed_requests')
                            ->label('Completed Requests')
                            ->content(fn ($record): string => $record ? 
                                number_format($record->requests()->where('status', 'completed')->count()) : '0')
                            ->visible(fn ($context) => $context === 'edit' || $context === 'view'),

                        Forms\Components\Placeholder::make('section_head')
                            ->label('Section Head')
                            ->content(fn ($record): string => $record ? 
                                ($record->getSectionHead()?->name ?? 'Not assigned') : 'N/A')
                            ->visible(fn ($context) => $context === 'edit' || $context === 'view'),
                    ])->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Company')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Department')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Department $record): string => "Code: {$record->code}"),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('secondary')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Users')
                    ->getStateUsing(fn (Department $record): int => $record->users()->count())
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        $state === 0 => 'danger',
                        $state <= 5 => 'warning',
                        default => 'success',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('section_head')
                    ->label('Section Head')
                    ->getStateUsing(fn (Department $record): string => $record->getSectionHead()?->name ?? 'Not assigned')
                    ->badge()
                    ->color(fn ($state): string => $state === 'Not assigned' ? 'danger' : 'success')
                    ->icon(fn ($state): string => $state === 'Not assigned' ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle'),

                Tables\Columns\TextColumn::make('active_requests')
                    ->label('Active')
                    ->getStateUsing(fn (Department $record): int => $record->getActiveRequestsCount())
                    ->badge()
                    ->color(fn ($state): string => $state > 0 ? 'warning' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('company_id')
                    ->label('Company')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('has_section_head')
                    ->label('Has Section Head')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereHas('users', function ($q) {
                            $q->role('section_head');
                        })
                    )
                    ->toggle(),

                Tables\Filters\Filter::make('no_section_head')
                    ->label('Missing Section Head')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereDoesntHave('users', function ($q) {
                            $q->role('section_head');
                        })
                    )
                    ->toggle(),

                Tables\Filters\Filter::make('active_departments')
                    ->label('Has Users')
                    ->query(fn (Builder $query): Builder => $query->whereHas('users'))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->color('info'),

                Tables\Actions\EditAction::make()
                    ->color('warning'),

                Action::make('assign_section_head')
                    ->label('Assign Head')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->visible(fn (Department $record): bool => !$record->getSectionHead())
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('Select Section Head')
                            ->options(function (Department $record) {
                                return $record->users()
                                    ->whereDoesntHave('roles', function ($q) {
                                        $q->where('name', 'section_head');
                                    })
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (array $data, Department $record): void {
                        $user = $record->users()->find($data['user_id']);
                        if ($user && $record->assignSectionHead($user)) {
                            Notification::make()
                                ->title('Section Head Assigned')
                                ->body("Successfully assigned {$user->name} as section head.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Assignment Failed')
                                ->body('Failed to assign section head.')
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Department $record): bool => 
                        $record->users()->count() === 0 && 
                        $record->requests()->count() === 0
                    )
                    ->modalDescription('This action will permanently delete the department. This action cannot be undone.')
                    ->modalSubmitActionLabel('Yes, delete it'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->can('manage_system'))
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('assignToCompany')
                        ->label('Move to Company')
                        ->icon('heroicon-o-building-office')
                        ->form([
                            Forms\Components\Select::make('company_id')
                                ->label('Target Company')
                                ->options(Company::active()->pluck('name', 'id'))
                                ->required()
                                ->searchable(),
                        ])
                        ->action(function (array $data, $records) {
                            foreach ($records as $record) {
                                $record->update(['company_id' => $data['company_id']]);
                            }
                            
                            Notification::make()
                                ->title('Departments moved successfully')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('name', 'asc')
            ->groups([
                Tables\Grouping\Group::make('company.name')
                    ->label('Company')
                    ->collapsible(),
            ]);
    }

    // ✅ FIX: Remove manual join, use Eloquent relationships instead
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['company', 'users' => function ($query) {
                $query->role('section_head');
            }]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'view' => Pages\ViewDepartment::route('/{record}'),
            'edit' => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'code', 'company.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Company' => $record->company?->name,
            'Code' => $record->code,
            'Users' => $record->users()->count() . ' users',
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return DepartmentResource::getUrl('view', ['record' => $record]);
    }
}