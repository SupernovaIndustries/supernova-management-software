<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectTaskResource\Pages;
use App\Models\ProjectTask;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Carbon\Carbon;

class ProjectTaskResource extends Resource
{
    protected static ?string $model = ProjectTask::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    
    protected static ?string $navigationLabel = 'Project Tasks';
    
    protected static ?string $navigationGroup = 'Project Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Task Information')
                    ->schema([
                        Forms\Components\Select::make('project_id')
                            ->relationship('project', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->label('Project'),
                            
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Task Name'),
                            
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                            
                        Forms\Components\Select::make('assigned_to')
                            ->relationship('assignedUser', 'name')
                            ->searchable()
                            ->preload()
                            ->label('Assigned To'),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Schedule & Progress')
                    ->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                $endDate = $get('end_date');
                                if ($state && $endDate) {
                                    $start = Carbon::parse($state);
                                    $end = Carbon::parse($endDate);
                                    $set('duration_days', $start->diffInDays($end) + 1);
                                }
                            }),
                            
                        Forms\Components\DatePicker::make('end_date')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                $startDate = $get('start_date');
                                if ($state && $startDate) {
                                    $start = Carbon::parse($startDate);
                                    $end = Carbon::parse($state);
                                    $set('duration_days', $start->diffInDays($end) + 1);
                                }
                            }),
                            
                        Forms\Components\TextInput::make('duration_days')
                            ->label('Duration (Days)')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),
                            
                        Forms\Components\TextInput::make('progress_percentage')
                            ->label('Progress %')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default(0),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Task Properties')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(ProjectTask::getStatusOptions())
                            ->required()
                            ->default('not_started'),
                            
                        Forms\Components\Select::make('priority')
                            ->options(ProjectTask::getPriorityOptions())
                            ->required()
                            ->default('medium'),
                            
                        Forms\Components\ColorPicker::make('color')
                            ->label('Gantt Chart Color')
                            ->default('#3498db'),
                            
                        Forms\Components\Toggle::make('is_milestone')
                            ->label('Is Milestone')
                            ->helperText('Mark as milestone for important project markers'),
                            
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(3),
                    
                Forms\Components\Section::make('Time Tracking')
                    ->schema([
                        Forms\Components\TextInput::make('estimated_hours')
                            ->label('Estimated Hours')
                            ->numeric()
                            ->step(0.5)
                            ->suffix('h'),
                            
                        Forms\Components\TextInput::make('actual_hours')
                            ->label('Actual Hours')
                            ->numeric()
                            ->step(0.5)
                            ->suffix('h'),
                            
                        Forms\Components\DatePicker::make('actual_start_date')
                            ->label('Actual Start Date'),
                            
                        Forms\Components\DatePicker::make('actual_end_date')
                            ->label('Actual End Date'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
                    
                Forms\Components\Section::make('Dependencies')
                    ->schema([
                        Forms\Components\Placeholder::make('dependencies_info')
                            ->label('Task Dependencies')
                            ->content('Dependencies can be managed in the Task Dependencies section. Use the Gantt chart view for visual dependency management.')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record?->exists)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('start_date', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('project.name')
                    ->label('Project')
                    ->searchable()
                    ->sortable()
                    ->limit(20),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('Task')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('assignedUser.name')
                    ->label('Assigned To')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Unassigned'),
                    
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Start')
                    ->date()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('end_date')
                    ->label('End')
                    ->date()
                    ->sortable()
                    ->color(fn (ProjectTask $record) => $record->isOverdue() ? 'danger' : null),
                    
                Tables\Columns\TextColumn::make('duration_days')
                    ->label('Duration')
                    ->suffix(' days')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label('Progress')
                    ->suffix('%')
                    ->sortable()
                    ->color(fn (ProjectTask $record) => match(true) {
                        $record->progress_percentage >= 100 => 'success',
                        $record->progress_percentage >= 75 => 'info',
                        $record->progress_percentage >= 50 => 'warning',
                        $record->progress_percentage > 0 => 'primary',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'not_started' => 'gray',
                        'in_progress' => 'primary',
                        'completed' => 'success',
                        'on_hold' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'low' => 'success',
                        'medium' => 'warning',
                        'high' => 'danger',
                        'critical' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),
                    
                Tables\Columns\IconColumn::make('is_milestone')
                    ->label('Milestone')
                    ->boolean()
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('estimated_hours')
                    ->label('Est. Hours')
                    ->suffix('h')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('actual_hours')
                    ->label('Actual Hours')
                    ->suffix('h')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('project')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('status')
                    ->options(ProjectTask::getStatusOptions()),
                    
                Tables\Filters\SelectFilter::make('priority')
                    ->options(ProjectTask::getPriorityOptions()),
                    
                Tables\Filters\SelectFilter::make('assigned_to')
                    ->relationship('assignedUser', 'name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue Tasks')
                    ->query(fn ($query) => $query->overdue()),
                    
                Tables\Filters\Filter::make('milestones')
                    ->label('Milestones Only')
                    ->query(fn ($query) => $query->where('is_milestone', true)),
            ])
            ->actions([
                Tables\Actions\Action::make('update_progress')
                    ->label('Update Progress')
                    ->icon('heroicon-o-chart-bar')
                    ->color('primary')
                    ->form([
                        Forms\Components\TextInput::make('progress')
                            ->label('Progress Percentage')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required()
                            ->default(fn (ProjectTask $record) => $record->progress_percentage),
                    ])
                    ->action(function (ProjectTask $record, array $data) {
                        $record->updateProgress($data['progress']);
                        
                        Notification::make()
                            ->title('Progress Updated')
                            ->body("Task progress updated to {$data['progress']}%")
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\Action::make('gantt_view')
                    ->label('Gantt Chart')
                    ->icon('heroicon-o-chart-bar-square')
                    ->color('info')
                    ->url(fn (ProjectTask $record) => route('filament.admin.pages.gantt-chart', ['project' => $record->project_id]))
                    ->openUrlInNewTab(),
                    
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('bulk_update_status')
                        ->label('Update Status')
                        ->icon('heroicon-o-pencil-square')
                        ->form([
                            Forms\Components\Select::make('status')
                                ->options(ProjectTask::getStatusOptions())
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            foreach ($records as $record) {
                                $record->update(['status' => $data['status']]);
                            }
                            
                            Notification::make()
                                ->title('Status Updated')
                                ->body(count($records) . ' tasks updated successfully')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('No Tasks')
            ->emptyStateDescription('Create your first project task to get started with Gantt charts.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Create Task'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectTasks::route('/'),
            'create' => Pages\CreateProjectTask::route('/create'),
            'edit' => Pages\EditProjectTask::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'in_progress')->count();
    }
}