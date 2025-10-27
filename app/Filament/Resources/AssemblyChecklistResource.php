<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssemblyChecklistResource\Pages;
use App\Filament\Resources\AssemblyChecklistResource\RelationManagers;
use App\Models\AssemblyChecklist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AssemblyChecklistResource extends Resource
{
    protected static ?string $model = AssemblyChecklist::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('template_id')
                    ->relationship('template', 'name')
                    ->required(),
                Forms\Components\Select::make('project_id')
                    ->relationship('project', 'name')
                    ->required(),
                Forms\Components\TextInput::make('board_serial_number')
                    ->maxLength(255),
                Forms\Components\TextInput::make('batch_number')
                    ->maxLength(255),
                Forms\Components\TextInput::make('board_quantity')
                    ->required()
                    ->numeric()
                    ->default(1),
                Forms\Components\TextInput::make('status')
                    ->required()
                    ->maxLength(255)
                    ->default('not_started'),
                Forms\Components\TextInput::make('assigned_to')
                    ->numeric(),
                Forms\Components\Select::make('supervisor_id')
                    ->relationship('supervisor', 'name'),
                Forms\Components\DateTimePicker::make('started_at'),
                Forms\Components\DateTimePicker::make('completed_at'),
                Forms\Components\TextInput::make('completion_percentage')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('total_items')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('completed_items')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('failed_items')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('board_specifications'),
                Forms\Components\Toggle::make('requires_supervisor_approval')
                    ->required(),
                Forms\Components\TextInput::make('approved_by')
                    ->numeric(),
                Forms\Components\DateTimePicker::make('approved_at'),
                Forms\Components\Textarea::make('failure_reason')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('template.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('project.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('board_serial_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('batch_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('board_quantity')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('assigned_to')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('supervisor.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('completion_percentage')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_items')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_items')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('failed_items')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('requires_supervisor_approval')
                    ->boolean(),
                Tables\Columns\TextColumn::make('approved_by')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('approved_at')
                    ->dateTime()
                    ->sortable(),
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
            'index' => Pages\ListAssemblyChecklists::route('/'),
            'create' => Pages\CreateAssemblyChecklist::route('/create'),
            'edit' => Pages\EditAssemblyChecklist::route('/{record}/edit'),
        ];
    }
}
