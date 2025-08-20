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
                                        $suggestedCode = 'DEPT' . str_pad($deptCount + 1, 2, '0', STR_PAD_LEFT);
                                        $set('code', $suggestedCode);
                                    }
                                }
                            })
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('code')
                                    ->required()
                                    ->maxLength(10)
                                    ->unique('companies', 'code'),
                                Forms\Components\Toggle::make('is_active')
                                    ->default(true),
                            ])
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Engineering, Operations, Human Resources, etc.')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                // Auto-generate code from name if not set
                                if ($state && !$get('code')) {
                                    $code = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $state), 0, 3));
                                    $set('code', $code);
                                }
                            })
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->maxLength(10)
                            ->placeholder('ENG, OPS, HR, etc.')
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
                            ->content(fn ($record): string => $record?->getSectionHead()?->name ?? 'Not assigned')
                            ->visible(fn ($context) => $context === 'edit' || $context === 'view'),
                    ])
                    ->columns(4)
                    ->visible(fn ($context) => $context !== 'create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company.code')
                    ->label('Company')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('secondary')
                    ->copyable(),

                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('active_requests_count')
                    ->label('Active Requests')
                    ->getStateUsing(fn ($record) => $record->requests()->whereNotIn('status', ['completed', 'rejected'])->count())
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('completed_requests_count')
                    ->label('Completed')
                    ->getStateUsing(fn ($record) => $record->requests()->where('status', 'completed')->count())
                    ->badge()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('section_head.name')
                    ->label('Section Head')
                    ->getStateUsing(fn ($record) => $record->getSectionHead()?->name)
                    ->placeholder('Not assigned')
                    ->icon('heroicon-m-user-circle')
                    ->iconColor('primary')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('company')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->label('Company Filter'),

                SelectFilter::make('has_section_head')
                    ->label('Section Head Status')
                    ->options([
                        'yes' => 'Has Section Head',
                        'no' => 'No Section Head',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] === 'yes',
                            fn (Builder $query): Builder => $query->whereHas('users', function ($q) {
                                $q->role('section_head');
                            }),
                            fn (Builder $query): Builder => $query->when(
                                $data['value'] === 'no',
                                fn (Builder $query): Builder => $query->whereDoesntHave('users', function ($q) {
                                    $q->role('section_head');
                                })
                            )
                        );
                    }),

                Tables\Filters\Filter::make('active_departments')
                    ->label('With Active Requests')
                    ->query(fn (Builder $query): Builder => $query->whereHas('requests', function ($q) {
                        $q->whereNotIn('status', ['completed', 'rejected']);
                    }))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Action::make('assignSectionHead')
                    ->label('Assign Section Head')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->visible(fn (Department $record): bool => !$record->getSectionHead())
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('Select User')
                            ->options(function (Department $record) {
                                return $record->users()
                                    ->whereDoesntHave('roles', function ($q) {
                                        $q->where('name', 'section_head');
                                    })
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->helperText('Select a user from this department to assign as Section Head'),
                    ])
                    ->action(function (Department $record, array $data) {
                        $user = \App\Models\User::find($data['user_id']);
                        if ($user) {
                            $user->assignRole('section_head');
                            
                            Notification::make()
                                ->title('Section Head assigned successfully')
                                ->body("{$user->name} is now the Section Head of {$record->name}")
                                ->success()
                                ->send();
                        }
                    }),

                Action::make('viewUsers')
                    ->label('View Users')
                    ->icon('heroicon-o-users')
                    ->color('info')
                    ->url(fn (Department $record): string => "/admin/users?tableFilters[department][value]={$record->id}")
                    ->openUrlInNewTab(),

                Action::make('viewRequests')
                    ->label('View Requests')
                    ->icon('heroicon-o-document-text')
                    ->color('warning')
                    ->url(fn (Department $record): string => "/admin/requests?tableFilters[department][value]={$record->id}")
                    ->openUrlInNewTab(),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Department $record) => $record->users()->count() === 0)
                    ->requiresConfirmation()
                    ->modalHeading('Delete Department')
                    ->modalDescription('Are you sure you want to delete this department? This action cannot be undone.')
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
            // ✅ FIXED: Use column that exists in departments table
            ->defaultSort('name', 'asc')
            ->groups([
                Tables\Grouping\Group::make('company.name')
                    ->label('Company')
                    ->collapsible(),
            ]);
    }

    // ✅ FIXED: Proper eager loading with join
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['company', 'users'])
            ->join('companies', 'companies.id', '=', 'departments.company_id')
            ->select('departments.*', 'companies.name as company_name', 'companies.code as company_code');
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

    public static function getNavigationBadge(): ?string
    {
        $activeCount = static::getEloquentQuery()
            ->whereHas('requests', function ($q) {
                $q->whereNotIn('status', ['completed', 'rejected']);
            })
            ->count();

        return $activeCount > 0 ? (string) $activeCount : null;
    }
}