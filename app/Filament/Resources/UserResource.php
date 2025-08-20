<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\Department;
use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model; 
use Illuminate\Support\Facades\Hash;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Section;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Users';
    protected static ?string $navigationGroup = 'User Management';
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_system') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Personal Information')
                    ->description('Basic user information and credentials')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('employee_id')
                            ->label('Employee ID')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('EMP001')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('position')
                            ->maxLength(255)
                            ->placeholder('Staff, Manager, etc.')
                            ->columnSpan(1),
                    ])->columns(4),

                Section::make('Company & Department')
                    ->description('Organizational assignment and access rights')
                    ->schema([
                        Forms\Components\Select::make('company_id')
                            ->label('Company')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                // Reset department when company changes
                                $set('department_id', null);
                            })
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('code')
                                    ->required()
                                    ->maxLength(10)
                                    ->unique('companies', 'code'),
                                Forms\Components\TextInput::make('address')
                                    ->maxLength(500),
                                Forms\Components\TextInput::make('phone')
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->maxLength(255),
                            ])
                            ->columnSpan(2),

                        Forms\Components\Select::make('department_id')
                            ->label('Department')
                            ->options(function (Forms\Get $get): array {
                                $companyId = $get('company_id');
                                
                                if (!$companyId) {
                                    return [];
                                }
                                
                                return Department::where('company_id', $companyId)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->live()
                            ->required()
                            ->disabled(fn (Forms\Get $get): bool => !$get('company_id'))
                            ->helperText('Select a company first to see available departments')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('code')
                                    ->required()
                                    ->maxLength(10),
                                Forms\Components\Select::make('company_id')
                                    ->relationship('company', 'name')
                                    ->required(),
                            ])
                            ->columnSpan(2),

                        Forms\Components\Select::make('roles')
                            ->label('User Roles')
                            ->multiple()
                            ->searchable()
                            ->options(fn (): array => Role::pluck('name', 'name')->toArray())
                            ->helperText('Select one or more roles for this user')
                            ->columnSpanFull(),
                    ])->columns(4),

                Section::make('Security')
                    ->description('Password and verification settings')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(8)
                            ->helperText('Leave blank to keep current password (for edit)')
                            ->revealable()
                            ->columnSpan(2),

                        Forms\Components\Toggle::make('email_verified_at')
                            ->label('Email Verified')
                            ->helperText('Whether the user has verified their email address')
                            ->dehydrateStateUsing(fn ($state) => $state ? now() : null)
                            ->formatStateUsing(fn ($state) => !is_null($state))
                            ->columnSpan(2),
                    ])->columns(4),

                Section::make('Additional Information')
                    ->description('Optional user details and settings')
                    ->schema([
                        Forms\Components\FileUpload::make('signature_path')
                            ->label('Digital Signature')
                            ->image()
                            ->directory('signatures')
                            ->maxSize(2048)
                            ->helperText('Upload digital signature image (max 2MB)')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee_id')
                    ->label('Employee ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (User $record): string => $record->email),

                Tables\Columns\TextColumn::make('company.name')
                    ->label('Company')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('department.name')
                    ->label('Department')
                    ->badge()
                    ->color('secondary')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('position')
                    ->label('Position')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->color('success')
                    ->separator(',')
                    ->searchable(),

                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Company')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('email_verified_at')
                    ->label('Email Verified')
                    ->placeholder('All users')
                    ->trueLabel('Verified users')
                    ->falseLabel('Unverified users'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->color('info'),

                Tables\Actions\EditAction::make()
                    ->color('warning'),

                Action::make('resetPassword')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reset Password')
                    ->modalDescription('Generate a new password for this user. The user will be notified via email.')
                    ->form([
                        Forms\Components\TextInput::make('new_password')
                            ->label('New Password')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->revealable(),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update([
                            'password' => Hash::make($data['new_password'])
                        ]);
                        
                        Notification::make()
                            ->title('Password Reset')
                            ->body("Password updated for {$record->name}")
                            ->success()
                            ->send();
                    }),

                Action::make('assignRole')
                    ->label('Assign Role')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('role')
                            ->label('Role')
                            ->options(Role::pluck('name', 'name'))
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->assignRole($data['role']);
                        
                        Notification::make()
                            ->title('Role Assigned')
                            ->body("Role {$data['role']} assigned to {$record->name}")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (User $record): bool => 
                        $record->requests()->count() === 0 && 
                        $record->approvals()->count() === 0
                    )
                    ->requiresConfirmation()
                    ->modalDescription('This action will permanently delete the user and cannot be undone.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->can('manage_system'))
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('assignRole')
                        ->label('Assign Role to Selected')
                        ->icon('heroicon-o-user-plus')
                        ->form([
                            Forms\Components\Select::make('role')
                                ->label('Role')
                                ->options(Role::pluck('name', 'name'))
                                ->required()
                                ->searchable(),
                        ])
                        ->action(function (array $data, $records) {
                            foreach ($records as $record) {
                                $record->assignRole($data['role']);
                            }
                            
                            Notification::make()
                                ->title('Roles Assigned')
                                ->body("Role {$data['role']} assigned to selected users")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('moveToCompany')
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
                                $record->update([
                                    'company_id' => $data['company_id'],
                                    'department_id' => null, // Reset department
                                ]);
                            }
                            
                            Notification::make()
                                ->title('Users Moved')
                                ->body('Selected users moved to new company')
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
                Tables\Grouping\Group::make('department.name')
                    ->label('Department')
                    ->collapsible(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['company', 'department', 'roles']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'employee_id', 'company.name', 'department.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Employee ID' => $record->employee_id,
            'Company' => $record->company?->name,
            'Department' => $record->department?->name,
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return UserResource::getUrl('view', ['record' => $record]);
    }
}