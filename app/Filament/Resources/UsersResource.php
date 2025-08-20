<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\Department;
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
        return auth()->user()->can('manage_system');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Personal Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('employee_id')
                            ->label('Employee ID')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('EMP001'),

                        Forms\Components\TextInput::make('position')
                            ->maxLength(255)
                            ->placeholder('Staff, Manager, etc.'),
                    ])->columns(2),

                Section::make('Department & Role')
                    ->schema([
                        Forms\Components\Select::make('department_id')
                            ->relationship('department', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('code')
                                    ->required()
                                    ->maxLength(10)
                                    ->unique('departments', 'code'),
                            ]),

                        Forms\Components\Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->options(fn () => Role::pluck('name', 'name'))
                            ->helperText('Select one or more roles for this user'),
                    ])->columns(2),

                Section::make('Security')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->maxLength(255)
                            ->revealable()
                            ->helperText('Leave blank to keep current password'),

                        Forms\Components\Toggle::make('email_verified')
                            ->label('Email Verified')
                            ->default(true)
                            ->helperText('Whether the user has verified their email address'),
                    ])->columns(2),

                Section::make('Additional Information')
                    ->schema([
                        Forms\Components\FileUpload::make('signature_path')
                            ->label('Digital Signature')
                            ->image()
                            ->directory('signatures')
                            ->visibility('private')
                            ->helperText('Upload user signature for documents'),

                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Additional notes about this user'),
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
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('department.name')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('position')
                    ->searchable()
                    ->placeholder('Not specified'),

                Tables\Columns\TextColumn::make('roles.name')
                    ->badge()
                    ->colors([
                        'danger' => 'admin',
                        'success' => 'pjo',
                        'warning' => ['section_head', 'scm_head'],
                        'primary' => 'user',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),

                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('requests_count')
                    ->counts('requests')
                    ->label('Requests')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department')
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Action::make('resetPassword')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Reset Password')
                    ->modalDescription('Generate a new password for this user')
                    ->form([
                        Forms\Components\TextInput::make('new_password')
                            ->label('New Password')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->revealable()
                            ->default(fn () => \Illuminate\Support\Str::random(12)),
                        
                        Forms\Components\Toggle::make('notify_user')
                            ->label('Send new password via email')
                            ->default(true),
                    ])
                    ->action(function (User $record, array $data) {
                        $record->update([
                            'password' => Hash::make($data['new_password'])
                        ]);

                        if ($data['notify_user']) {
                            // TODO: Send email with new password
                            // $record->notify(new NewPasswordNotification($data['new_password']));
                        }

                        Notification::make()
                            ->title('Password reset successfully')
                            ->success()
                            ->body("New password: {$data['new_password']}")
                            ->send();
                    }),

                Action::make('toggleStatus')
                    ->label(fn (User $record) => $record->deleted_at ? 'Activate' : 'Deactivate')
                    ->icon(fn (User $record) => $record->deleted_at ? 'heroicon-o-play' : 'heroicon-o-pause')
                    ->color(fn (User $record) => $record->deleted_at ? 'success' : 'danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => $record->id !== auth()->id()) // Can't deactivate self
                    ->action(function (User $record) {
                        if ($record->deleted_at) {
                            $record->restore();
                            $message = 'User activated successfully';
                        } else {
                            $record->delete();
                            $message = 'User deactivated successfully';
                        }

                        Notification::make()
                            ->title($message)
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (User $record) => $record->id !== auth()->id()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add New User')
                    ->icon('heroicon-o-plus'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('assignRole')
                        ->label('Assign Role')
                        ->icon('heroicon-o-user-group')
                        ->form([
                            Forms\Components\Select::make('role')
                                ->options(Role::pluck('name', 'name'))
                                ->required(),
                        ])
                        ->action(function (array $data, $records) {
                            foreach ($records as $record) {
                                $record->assignRole($data['role']);
                            }
                            
                            Notification::make()
                                ->title('Role assigned to selected users')
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
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

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['department']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'employee_id', 'department.name'];
    }

    // Fix method di baris 309 - ganti dengan ini:
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Department' => $record->department?->name,
            'Position' => $record->position,
            'Employee ID' => $record->employee_id,
        ];
    }
}
