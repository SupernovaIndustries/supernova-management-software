<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TimeEntryResource\Pages;
use App\Models\TimeEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Carbon\Carbon;

class TimeEntryResource extends Resource
{
    protected static ?string $model = TimeEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    
    protected static ?string $navigationLabel = 'Time Tracking';
    
    protected static ?string $navigationGroup = 'Project Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Time Entry Details')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn () => auth()->id())
                            ->label('User'),
                            
                        Forms\Components\Select::make('project_id')
                            ->relationship('project', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (Set $set) => $set('project_task_id', null))
                            ->label('Project'),
                            
                        Forms\Components\Select::make('project_task_id')
                            ->options(function (Get $get) {
                                $projectId = $get('project_id');
                                if (!$projectId) {
                                    return [];
                                }
                                
                                return \App\Models\ProjectTask::where('project_id', $projectId)
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->label('Task (Optional)')
                            ->helperText('Leave empty to log time at project level'),
                            
                        Forms\Components\DatePicker::make('date')
                            ->required()
                            ->default(now())
                            ->maxDate(now())
                            ->label('Date'),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Time Tracking')
                    ->schema([
                        Forms\Components\Group::make([
                            Forms\Components\TimePicker::make('start_time')
                                ->label('Start Time')
                                ->reactive()
                                ->seconds(false)
                                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                    $endTime = $get('end_time');
                                    if ($state && $endTime) {
                                        $start = Carbon::parse($state);
                                        $end = Carbon::parse($endTime);
                                        $hours = $end->diffInMinutes($start) / 60;
                                        $set('hours', number_format($hours, 2));
                                    }
                                }),
                                
                            Forms\Components\TimePicker::make('end_time')
                                ->label('End Time')
                                ->reactive()
                                ->seconds(false)
                                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                    $startTime = $get('start_time');
                                    if ($state && $startTime) {
                                        $start = Carbon::parse($startTime);
                                        $end = Carbon::parse($state);
                                        $hours = $end->diffInMinutes($start) / 60;
                                        $set('hours', number_format($hours, 2));
                                    }
                                }),
                        ])->columns(2),
                        
                        Forms\Components\TextInput::make('hours')
                            ->label('Total Hours')
                            ->numeric()
                            ->step(0.25)
                            ->minValue(0)
                            ->maxValue(24)
                            ->suffix('h')
                            ->required()
                            ->helperText('Enter hours directly or use start/end times above'),
                    ]),
                    
                Forms\Components\Section::make('Work Details')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->options(TimeEntry::getTypeOptions())
                            ->required()
                            ->default('development'),
                            
                        Forms\Components\Textarea::make('description')
                            ->label('Work Description')
                            ->rows(3)
                            ->required()
                            ->helperText('Describe what work was performed')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Billing & Approval')
                    ->schema([
                        Forms\Components\Toggle::make('is_billable')
                            ->label('Billable')
                            ->default(true)
                            ->reactive(),
                            
                        Forms\Components\TextInput::make('hourly_rate')
                            ->label('Hourly Rate (€)')
                            ->numeric()
                            ->step(0.01)
                            ->prefix('€')
                            ->visible(fn (Get $get) => $get('is_billable'))
                            ->helperText('Leave empty to use default user rate'),
                            
                        Forms\Components\Select::make('status')
                            ->options(TimeEntry::getStatusOptions())
                            ->required()
                            ->default('draft')
                            ->disabled(fn ($record) => $record && !$record->canBeEdited()),
                            
                        Forms\Components\Placeholder::make('billable_amount')
                            ->label('Billable Amount')
                            ->content(fn ($record) => $record ? '€ ' . number_format($record->billable_amount, 2) : '€ 0.00')
                            ->visible(fn ($record) => $record?->exists),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Approval Details')
                    ->schema([
                        Forms\Components\Select::make('approved_by')
                            ->relationship('approvedBy', 'name')
                            ->disabled()
                            ->visible(fn ($record) => $record?->approved_by),
                            
                        Forms\Components\DateTimePicker::make('approved_at')
                            ->label('Approved At')
                            ->disabled()
                            ->visible(fn ($record) => $record?->approved_at),
                            
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->rows(2)
                            ->disabled()
                            ->visible(fn ($record) => $record?->rejection_reason)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record?->exists && in_array($record->status, ['approved', 'rejected']))
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('project.name')
                    ->label('Project')
                    ->searchable()
                    ->sortable()
                    ->limit(25),
                    
                Tables\Columns\TextColumn::make('task.name')
                    ->label('Task')
                    ->searchable()
                    ->sortable()
                    ->limit(20)
                    ->placeholder('Project Level'),
                    
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Start')
                    ->time('H:i')
                    ->placeholder('-'),
                    
                Tables\Columns\TextColumn::make('end_time')
                    ->label('End')
                    ->time('H:i')
                    ->placeholder('-'),
                    
                Tables\Columns\TextColumn::make('hours')
                    ->label('Hours')
                    ->suffix('h')
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'development' => 'success',
                        'testing' => 'warning',
                        'design' => 'info',
                        'meeting' => 'primary',
                        'documentation' => 'secondary',
                        'research' => 'gray',
                        'other' => 'danger',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('description')
                    ->limit(30)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),
                    
                Tables\Columns\IconColumn::make('is_billable')
                    ->label('Billable')
                    ->boolean()
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('billable_amount')
                    ->label('Amount')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'submitted' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('project')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('status')
                    ->options(TimeEntry::getStatusOptions()),
                    
                Tables\Filters\SelectFilter::make('type')
                    ->options(TimeEntry::getTypeOptions()),
                    
                Tables\Filters\Filter::make('billable')
                    ->label('Billable Only')
                    ->query(fn ($query) => $query->billable()),
                    
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From Date'),
                        Forms\Components\DatePicker::make('until')->label('Until Date'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($query, $date) => $query->whereDate('date', '>=', $date))
                            ->when($data['until'], fn ($query, $date) => $query->whereDate('date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('submit')
                    ->label('Submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn (TimeEntry $record) => $record->status === 'draft')
                    ->action(function (TimeEntry $record) {
                        $record->submit();
                        
                        Notification::make()
                            ->title('Time Entry Submitted')
                            ->body('Time entry submitted for approval')
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (TimeEntry $record) => $record->canBeApproved())
                    ->action(function (TimeEntry $record) {
                        $record->approve(auth()->id());
                        
                        Notification::make()
                            ->title('Time Entry Approved')
                            ->body('Time entry has been approved')
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (TimeEntry $record) => $record->canBeApproved())
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (TimeEntry $record, array $data) {
                        $record->reject(auth()->id(), $data['reason']);
                        
                        Notification::make()
                            ->title('Time Entry Rejected')
                            ->body('Time entry has been rejected')
                            ->warning()
                            ->send();
                    }),
                    
                Tables\Actions\EditAction::make()
                    ->visible(fn (TimeEntry $record) => $record->canBeEdited()),
                    
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (TimeEntry $record) => $record->canBeEdited()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('bulk_submit')
                        ->label('Submit for Approval')
                        ->icon('heroicon-o-paper-airplane')
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'draft') {
                                    $record->submit();
                                    $count++;
                                }
                            }
                            
                            Notification::make()
                                ->title('Time Entries Submitted')
                                ->body("$count time entries submitted for approval")
                                ->success()
                                ->send();
                        }),
                        
                    Tables\Actions\BulkAction::make('bulk_approve')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->canBeApproved()) {
                                    $record->approve(auth()->id());
                                    $count++;
                                }
                            }
                            
                            Notification::make()
                                ->title('Time Entries Approved')
                                ->body("$count time entries approved")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('No Time Entries')
            ->emptyStateDescription('Start tracking time by creating your first time entry.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Log Time'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTimeEntries::route('/'),
            'create' => Pages\CreateTimeEntry::route('/create'),
            'edit' => Pages\EditTimeEntry::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::pendingApproval()->count();
    }
}