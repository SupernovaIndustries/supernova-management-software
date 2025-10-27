<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\Milestone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MilestonesRelationManager extends RelationManager
{
    protected static string $relationship = 'milestones';
    
    protected static ?string $title = 'Milestone del Progetto';
    
    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('milestone_id')
                    ->label('Milestone')
                    ->options(Milestone::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                    ->columnSpan(2),
                    
                Forms\Components\DatePicker::make('target_date')
                    ->label('Data Target')
                    ->helperText('Data prevista per il completamento'),
                    
                Forms\Components\DatePicker::make('completed_date')
                    ->label('Data Completamento')
                    ->helperText('Data effettiva di completamento'),
                    
                Forms\Components\Toggle::make('is_completed')
                    ->label('Completata')
                    ->reactive()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state && !$set('completed_date')) {
                            $set('completed_date', now()->format('Y-m-d'));
                        }
                    }),
                    
                Forms\Components\TextInput::make('sort_order')
                    ->label('Ordine')
                    ->numeric()
                    ->default(0),
                    
                Forms\Components\Textarea::make('notes')
                    ->label('Note')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('project_milestone.sort_order', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome Milestone')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('category')
                    ->label('Categoria')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'design' => 'Progettazione',
                        'prototype' => 'Prototipo',
                        'testing' => 'Test',
                        'production' => 'Produzione',
                        'delivery' => 'Consegna',
                        'documentation' => 'Documentazione',
                        'approval' => 'Approvazione',
                        default => 'Altro',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'design' => 'info',
                        'prototype' => 'warning',
                        'testing' => 'primary',
                        'production' => 'success',
                        'delivery' => 'gray',
                        'documentation' => 'purple',
                        'approval' => 'danger',
                        default => 'secondary',
                    }),
                    
                Tables\Columns\TextColumn::make('pivot.target_date')
                    ->label('Data Target')
                    ->date('d/m/Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('pivot.completed_date')
                    ->label('Completata il')
                    ->date('d/m/Y')
                    ->placeholder('Non completata')
                    ->sortable(),
                    
                Tables\Columns\IconColumn::make('pivot.is_completed')
                    ->label('Stato')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('documents_count')
                    ->label('Documenti')
                    ->counts('documents')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-paper-clip')
                    ->placeholder('0')
                    ->tooltip(fn ($record): string =>
                        $record->documents_count > 0
                            ? "{$record->documents_count} documento/i allegato/i"
                            : 'Nessun documento allegato'
                    ),

                Tables\Columns\TextColumn::make('deadline')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->placeholder('Nessuna')
                    ->color(function ($record) {
                        if (!$record->deadline) return null;
                        $daysLeft = now()->diffInDays($record->deadline, false);
                        if ($daysLeft < 0) return 'danger';
                        if ($daysLeft <= 3) return 'warning';
                        return null;
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_completed')
                    ->label('Stato Completamento')
                    ->options([
                        '1' => 'Completate',
                        '0' => 'Da completare',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (isset($data['value']) && $data['value'] !== '') {
                            return $query->wherePivot('is_completed', $data['value']);
                        }
                        return $query;
                    }),
                    
                Tables\Filters\SelectFilter::make('category')
                    ->label('Categoria')
                    ->options([
                        'design' => 'Progettazione',
                        'prototype' => 'Prototipo',
                        'testing' => 'Test',
                        'production' => 'Produzione',
                        'delivery' => 'Consegna',
                        'documentation' => 'Documentazione',
                        'approval' => 'Approvazione',
                        'other' => 'Altro',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Aggiungi Milestone')
                    ->modalHeading('Aggiungi Milestone al Progetto')
                    ->modalSubmitActionLabel('Aggiungi')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'description'])
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->label('Seleziona Milestone')
                            ->placeholder('Cerca milestone...')
                            ->helperText('Le milestone possono essere condivise tra più progetti'),
                            
                        Forms\Components\DatePicker::make('target_date')
                            ->label('Data Target')
                            ->default(now()->addWeeks(2)),
                            
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Ordine')
                            ->numeric()
                            ->default(0),
                            
                        Forms\Components\Textarea::make('notes')
                            ->label('Note')
                            ->rows(2),
                    ]),
                    
                Tables\Actions\CreateAction::make()
                    ->label('Crea Nuova Milestone')
                    ->modalHeading('Crea Nuova Milestone')
                    ->modalSubmitActionLabel('Crea e Aggiungi')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Crea prima la milestone
                        $milestone = Milestone::create([
                            'name' => $data['name'],
                            'description' => $data['description'] ?? null,
                            'category' => $data['category'] ?? 'other',
                            'color' => $data['color'] ?? 'gray',
                            'is_active' => true,
                        ]);
                        
                        // Poi prepara i dati per l'attach
                        return [
                            'milestone_id' => $milestone->id,
                            'target_date' => $data['target_date'] ?? null,
                            'sort_order' => $data['sort_order'] ?? 0,
                            'notes' => $data['notes'] ?? null,
                        ];
                    })
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome Milestone')
                            ->required()
                            ->maxLength(255),
                            
                        Forms\Components\Textarea::make('description')
                            ->label('Descrizione')
                            ->rows(2),
                            
                        Forms\Components\Select::make('category')
                            ->label('Categoria')
                            ->options([
                                'design' => 'Progettazione',
                                'prototype' => 'Prototipo',
                                'testing' => 'Test',
                                'production' => 'Produzione',
                                'delivery' => 'Consegna',
                                'documentation' => 'Documentazione',
                                'approval' => 'Approvazione',
                                'other' => 'Altro',
                            ])
                            ->default('other'),
                            
                        Forms\Components\Select::make('color')
                            ->label('Colore')
                            ->options([
                                'gray' => 'Grigio',
                                'blue' => 'Blu',
                                'green' => 'Verde',
                                'yellow' => 'Giallo',
                                'orange' => 'Arancione',
                                'red' => 'Rosso',
                                'purple' => 'Viola',
                            ])
                            ->default('blue'),
                            
                        Forms\Components\DatePicker::make('target_date')
                            ->label('Data Target')
                            ->default(now()->addWeeks(2)),
                            
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Ordine')
                            ->numeric()
                            ->default(0),
                            
                        Forms\Components\Textarea::make('notes')
                            ->label('Note')
                            ->rows(2),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading('Modifica Milestone del Progetto')
                    ->modalSubmitActionLabel('Salva')
                    ->form(function ($record) {
                        return [
                            Forms\Components\TextInput::make('name')
                                ->label('Milestone')
                                ->disabled()
                                ->default($record->name),
                                
                            Forms\Components\DatePicker::make('pivot.target_date')
                                ->label('Data Target'),
                                
                            Forms\Components\DatePicker::make('pivot.completed_date')
                                ->label('Data Completamento'),
                                
                            Forms\Components\Toggle::make('pivot.is_completed')
                                ->label('Completata')
                                ->reactive()
                                ->afterStateUpdated(function ($state, Forms\Set $set, $get) {
                                    if ($state && !$get('pivot.completed_date')) {
                                        $set('pivot.completed_date', now()->format('Y-m-d'));
                                    }
                                }),
                                
                            Forms\Components\TextInput::make('pivot.sort_order')
                                ->label('Ordine')
                                ->numeric(),
                                
                            Forms\Components\Textarea::make('pivot.notes')
                                ->label('Note')
                                ->rows(3),
                        ];
                    }),
                    
                Tables\Actions\DetachAction::make()
                    ->label('Rimuovi')
                    ->modalHeading('Rimuovi Milestone dal Progetto')
                    ->modalDescription('Sei sicuro di voler rimuovere questa milestone dal progetto? La milestone non verrà eliminata, solo rimossa da questo progetto.'),
                    
                Tables\Actions\Action::make('toggleComplete')
                    ->label('Segna Completata')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->hidden(fn ($record) => $record->pivot->is_completed)
                    ->modalHeading('Completa Milestone')
                    ->modalDescription(fn ($record) => "Stai per completare la milestone: {$record->name}")
                    ->modalSubmitActionLabel('Completa')
                    ->form([
                        Forms\Components\FileUpload::make('documents')
                            ->label('Documenti (Report Test, QC, etc.)')
                            ->multiple()
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(10240) // 10MB
                            ->helperText('Carica report di test, certificazioni QC, o altri documenti relativi a questa milestone')
                            ->directory('project-documents/milestone-reports')
                            ->visibility('private')
                            ->preserveFilenames(),

                        Forms\Components\Textarea::make('completion_notes')
                            ->label('Note di Completamento')
                            ->rows(3)
                            ->helperText('Aggiungi eventuali note sul completamento di questa milestone'),
                    ])
                    ->action(function ($record, array $data) {
                        $project = $this->getOwnerRecord();

                        // Mark milestone as completed
                        $record->pivot->is_completed = true;
                        $record->pivot->completed_date = now();

                        // Add completion notes if provided
                        if (!empty($data['completion_notes'])) {
                            $existingNotes = $record->pivot->notes ?? '';
                            $record->pivot->notes = $existingNotes
                                ? $existingNotes . "\n\n--- Completamento " . now()->format('d/m/Y H:i') . " ---\n" . $data['completion_notes']
                                : $data['completion_notes'];
                        }

                        $record->pivot->save();

                        // Handle document uploads
                        $uploadedDocuments = [];
                        if (!empty($data['documents'])) {
                            foreach ($data['documents'] as $filePath) {
                                $file = new \Illuminate\Http\File(storage_path('app/' . $filePath));

                                $document = \App\Models\ProjectDocument::create([
                                    'project_id' => $project->id,
                                    'milestone_id' => $record->id,
                                    'name' => "Report Milestone: {$record->name}",
                                    'type' => 'test_report',
                                    'file_path' => $filePath,
                                    'original_filename' => $file->getFilename(),
                                    'mime_type' => mime_content_type($file->getPathname()),
                                    'file_size' => $file->getSize(),
                                    'description' => "Documento allegato al completamento della milestone {$record->name}",
                                    'document_date' => now(),
                                    'is_active' => true,
                                ]);

                                $uploadedDocuments[] = $document;
                            }
                        }

                        // Recalculate project completion percentage
                        $project->updateCompletionPercentage();

                        // Find next milestone (not completed, ordered by sort_order)
                        $nextMilestone = $project->milestones()
                            ->wherePivot('is_completed', false)
                            ->orderByPivot('sort_order')
                            ->first();

                        // Send completion email
                        $this->sendMilestoneCompletedEmail($project, $record, $nextMilestone, $uploadedDocuments);

                        $documentsCount = count($uploadedDocuments);
                        $documentsMessage = $documentsCount > 0
                            ? " {$documentsCount} documento/i allegato/i."
                            : '';

                        Notification::make()
                            ->title('Milestone Completata')
                            ->success()
                            ->body("Email di notifica inviata al cliente.{$documentsMessage}")
                            ->send();
                    })
                    ->after(fn () => $this->dispatch('$refresh')),
                    
                Tables\Actions\Action::make('toggleIncomplete')
                    ->label('Segna Non Completata')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn ($record) => $record->pivot->is_completed)
                    ->action(function ($record) {
                        $project = $this->getOwnerRecord();

                        $record->pivot->is_completed = false;
                        $record->pivot->completed_date = null;
                        $record->pivot->save();

                        // Recalculate project completion percentage
                        $project->updateCompletionPercentage();

                        Notification::make()
                            ->title('Milestone Riaperta')
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\Action::make('viewDocuments')
                    ->label('Documenti')
                    ->icon('heroicon-o-paper-clip')
                    ->color('info')
                    ->modalHeading(fn ($record) => "Documenti: {$record->name}")
                    ->modalContent(function ($record) {
                        $documents = $record->documents()->orderBy('created_at', 'desc')->get();

                        if ($documents->isEmpty()) {
                            return view('filament.components.empty-state', [
                                'heading' => 'Nessun documento',
                                'description' => 'Non ci sono documenti allegati a questa milestone.',
                                'icon' => 'heroicon-o-document',
                            ]);
                        }

                        return view('filament.components.milestone-documents', [
                            'documents' => $documents,
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Chiudi')
                    ->visible(fn ($record) => $record->documents_count > 0),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->label('Rimuovi Selezionate'),
                        
                    Tables\Actions\BulkAction::make('markCompleted')
                        ->label('Segna Come Completate')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(function ($records) {
                            $project = $this->getOwnerRecord();

                            foreach ($records as $record) {
                                $record->pivot->is_completed = true;
                                $record->pivot->completed_date = now();
                                $record->pivot->save();
                            }

                            // Recalculate project completion percentage
                            $project->updateCompletionPercentage();

                            Notification::make()
                                ->title('Milestone Completate')
                                ->success()
                                ->body(count($records) . ' milestone sono state completate.')
                                ->send();
                        }),
                ]),
            ]);
    }

    /**
     * Send email notification when milestone is completed.
     */
    private function sendMilestoneCompletedEmail($project, $completedMilestone, $nextMilestone, array $documents = []): void
    {
        try {
            $adminEmail = 'alessandro.cursoli@supernovaindustries.it';

            // Get client email from project or customer
            $clientEmail = $project->client_email ?: $project->customer->email;

            // Calculate completion percentage
            $completionPercentage = $project->completion_percentage ?? 0;

            // Always send to admin
            $mail = \Illuminate\Support\Facades\Mail::to($adminEmail);

            // CC to client if email is available
            if ($clientEmail) {
                $mail->cc($clientEmail);
            }

            $mail->send(new \App\Mail\MilestoneCompletedMail(
                $project,
                $completedMilestone,
                $nextMilestone,
                $completionPercentage,
                $documents
            ));

            \Illuminate\Support\Facades\Log::info("Milestone completed email sent", [
                'project_id' => $project->id,
                'project_code' => $project->code,
                'milestone' => $completedMilestone->name,
                'completion_percentage' => $completionPercentage,
                'documents_attached' => count($documents),
                'admin_email' => $adminEmail,
                'client_email' => $clientEmail ?? 'none',
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send milestone completed email", [
                'project_id' => $project->id,
                'project_code' => $project->code,
                'milestone' => $completedMilestone->name,
                'error' => $e->getMessage(),
            ]);
        }
    }
}