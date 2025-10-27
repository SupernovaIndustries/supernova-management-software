<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Resources\CategoryResource\RelationManagers;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('icon')
                    ->maxLength(255),
                Forms\Components\TextInput::make('color')
                    ->maxLength(255),
                Forms\Components\Select::make('parent_id')
                    ->relationship('parent', 'name'),
                Forms\Components\TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('icon')
                    ->searchable(),
                Tables\Columns\TextColumn::make('color')
                    ->searchable(),
                Tables\Columns\TextColumn::make('parent.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
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
                Tables\Actions\DeleteAction::make()
                    ->before(function (Category $record, $action) {
                        // Check for relationships before deletion
                        $relationships = static::checkCategoryRelationships($record);
                        if (!empty($relationships)) {
                            $message = "⚠️ **Impossibile eliminare la categoria**\n\n";
                            $message .= "**{$record->name}** è ancora utilizzata in:\n\n";
                            foreach ($relationships as $table => $count) {
                                $labels = [
                                    'components' => 'Componenti',
                                    'categories' => 'Sottocategorie',
                                ];
                                $label = $labels[$table] ?? $table;
                                $message .= "• **{$label}**: {$count} record\n";
                            }
                            $message .= "\n💡 **Soluzione**: Spostare prima questi elementi o contattare l'amministratore.";
                            
                            Notification::make()
                                ->title('Eliminazione Bloccata')
                                ->body($message)
                                ->danger()
                                ->persistent()
                                ->send();
                                
                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function ($records, $action) {
                            $blockedCategories = [];
                            $deletableCategories = [];
                            
                            foreach ($records as $category) {
                                $relationships = static::checkCategoryRelationships($category);
                                if (!empty($relationships)) {
                                    $relTypes = array_keys($relationships);
                                    $blockedCategories[] = [
                                        'name' => $category->name,
                                        'relationships' => $relTypes
                                    ];
                                } else {
                                    $deletableCategories[] = $category;
                                }
                            }
                            
                            if (!empty($blockedCategories)) {
                                $message = "⚠️ **Alcune categorie non possono essere eliminate:**\n\n";
                                foreach (array_slice($blockedCategories, 0, 5) as $blocked) {
                                    $message .= "• **{$blocked['name']}**\n";
                                }
                                if (count($blockedCategories) > 5) {
                                    $message .= "• ... e altre " . (count($blockedCategories) - 5) . " categorie\n";
                                }
                                
                                if (!empty($deletableCategories)) {
                                    $message .= "\n✅ **Eliminate con successo:** " . count($deletableCategories) . " categorie\n";
                                    // Delete only the safe categories
                                    foreach ($deletableCategories as $category) {
                                        $category->delete();
                                    }
                                }
                                
                                $message .= "\n💡 **Per eliminare le categorie bloccate**, spostare prima gli elementi collegati.";
                                
                                Notification::make()
                                    ->title('Eliminazione Parziale Completata')
                                    ->body($message)
                                    ->warning()
                                    ->duration(15000)
                                    ->send();
                            } else {
                                // All categories can be deleted safely
                                foreach ($deletableCategories as $category) {
                                    $category->delete();
                                }
                                
                                Notification::make()
                                    ->title('Eliminazione Completata')
                                    ->body('Tutte le ' . count($deletableCategories) . ' categorie sono state eliminate con successo.')
                                    ->success()
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Check if category has relationships that prevent deletion
     */
    protected static function checkCategoryRelationships(Category $category): array
    {
        $relationships = [];
        
        // Check components using this category
        $components = \DB::table('components')->where('category_id', $category->id)->count();
        if ($components > 0) {
            $relationships['components'] = $components;
        }
        
        // Check subcategories
        $subcategories = \DB::table('categories')->where('parent_id', $category->id)->count();
        if ($subcategories > 0) {
            $relationships['categories'] = $subcategories;
        }
        
        return $relationships;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
