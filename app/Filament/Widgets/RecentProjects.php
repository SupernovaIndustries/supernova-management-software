<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentProjects extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Project::query()
                    ->with(['customer', 'manager'])
                    ->active()
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Codice')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome Progetto')
                    ->searchable()
                    ->wrap()
                    ->limit(30),
                    
                Tables\Columns\TextColumn::make('customer.display_name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'planning' => 'gray',
                        'in_progress' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'secondary',
                    }),
                    
                Tables\Columns\TextColumn::make('priority')
                    ->label('Priorità')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'low' => 'gray',
                        'medium' => 'warning',
                        'high' => 'danger',
                        'urgent' => 'danger',
                        default => 'secondary',
                    }),
                    
                Tables\Columns\TextColumn::make('progress')
                    ->label('Progresso')
                    ->suffix('%')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Scadenza')
                    ->date()
                    ->sortable()
                    ->color(fn (Project $record): string => 
                        $record->isOverdue() ? 'danger' : 'secondary'
                    ),
                    
                Tables\Columns\TextColumn::make('manager.name')
                    ->label('Responsabile')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->paginated([5, 10])
            ->heading('Progetti Recenti')
            ->description('Ultimi progetti attivi')
            ->defaultSort('created_at', 'desc');
    }
}