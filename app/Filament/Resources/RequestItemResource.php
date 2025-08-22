<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RequestItemResource\Pages;
use App\Filament\Resources\RequestItemResource\RelationManagers;
use App\Models\RequestItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RequestItemResource extends Resource
{
    protected static ?string $model = RequestItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('request_id')
                    ->relationship('request', 'id')
                    ->required(),
                Forms\Components\TextInput::make('item_number')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('description')
                    ->required()
                    ->maxLength(255),
                //Forms\Components\TextInput::make('category')
                //    ->maxLength(100)
                //    ->default(null),
                Forms\Components\Textarea::make('specification')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('quantity')
                    ->required()
                    ->numeric(),
                //Forms\Components\TextInput::make('price')
                //    ->numeric()
                //    ->default(null)
                //    ->prefix('IDR'),
                Forms\Components\TextInput::make('unit_of_measurement')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('remarks')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('request.id')
                    ->numeric()
                    ->sortable(),
                //Tables\Columns\TextColumn::make('item_suggestion_id')
                //    ->numeric()
                //    ->sortable(),
                Tables\Columns\TextColumn::make('item_number')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable(),
                //Tables\Columns\TextColumn::make('category')
                //    ->searchable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                //Tables\Columns\TextColumn::make('price')
                //    ->money()
                //    ->sortable(),
                //Tables\Columns\TextColumn::make('total_price')
                //    ->numeric()
                //    ->sortable(),
                Tables\Columns\TextColumn::make('unit_of_measurement')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListRequestItems::route('/'),
            'create' => Pages\CreateRequestItem::route('/create'),
            'edit' => Pages\EditRequestItem::route('/{record}/edit'),
        ];
    }
}
