<?php

namespace App\Filament\Resources\ProjectPcbFileResource\Pages;

use App\Filament\Resources\ProjectPcbFileResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\Grid;
use Filament\Actions;
use App\Services\PcbVersionControlService;

class ViewProjectPcbFile extends ViewRecord
{
    protected static string $resource = ProjectPcbFileResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Split::make([
                    Section::make('File Information')
                        ->schema([
                            TextEntry::make('filename')
                                ->label('Filename'),
                            TextEntry::make('version')
                                ->badge()
                                ->color(fn ($state) => version_compare($state, '1.0.0', '>=') ? 'success' : 'warning'),
                            TextEntry::make('format')
                                ->badge(),
                            TextEntry::make('file_size')
                                ->formatStateUsing(fn ($state) => number_format($state / 1024 / 1024, 2) . ' MB'),
                            TextEntry::make('is_primary')
                                ->label('Primary Version')
                                ->badge()
                                ->color(fn ($state) => $state ? 'success' : 'gray')
                                ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
                            TextEntry::make('is_backup')
                                ->label('Backup')
                                ->badge()
                                ->color(fn ($state) => $state ? 'info' : 'gray')
                                ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
                        ])
                        ->columnSpan(1),
                    
                    Section::make('Version Details')
                        ->schema([
                            TextEntry::make('change_type')
                                ->badge()
                                ->color(fn ($state) => match($state) {
                                    'major' => 'danger',
                                    'minor' => 'warning',
                                    'patch' => 'success',
                                    default => 'gray',
                                }),
                            TextEntry::make('change_description')
                                ->columnSpanFull(),
                            TextEntry::make('uploadedBy.name')
                                ->label('Uploaded By'),
                            TextEntry::make('created_at')
                                ->label('Upload Date')
                                ->dateTime(),
                            TextEntry::make('checksum')
                                ->label('File Checksum')
                                ->copyable()
                                ->fontFamily('mono')
                                ->limit(20),
                        ])
                        ->columnSpan(1),
                ]),
                
                Section::make('Design Rule Check Results')
                    ->schema([
                        TextEntry::make('drc_results')
                            ->label('')
                            ->html()
                            ->formatStateUsing(function ($state) {
                                if (!$state) {
                                    return '<span class="text-gray-500">No DRC results available</span>';
                                }
                                
                                $results = json_decode($state, true);
                                if (!$results) {
                                    return '<span class="text-gray-500">Invalid DRC results</span>';
                                }
                                
                                $html = '<div class="space-y-2">';
                                $html .= '<div class="grid grid-cols-3 gap-4">';
                                $html .= '<div class="text-center p-2 bg-red-50 rounded">';
                                $html .= '<div class="text-2xl font-bold text-red-600">' . ($results['errors'] ?? 0) . '</div>';
                                $html .= '<div class="text-sm text-red-600">Errors</div>';
                                $html .= '</div>';
                                $html .= '<div class="text-center p-2 bg-yellow-50 rounded">';
                                $html .= '<div class="text-2xl font-bold text-yellow-600">' . ($results['warnings'] ?? 0) . '</div>';
                                $html .= '<div class="text-sm text-yellow-600">Warnings</div>';
                                $html .= '</div>';
                                $html .= '<div class="text-center p-2 bg-blue-50 rounded">';
                                $html .= '<div class="text-2xl font-bold text-blue-600">' . ($results['info'] ?? 0) . '</div>';
                                $html .= '<div class="text-sm text-blue-600">Info</div>';
                                $html .= '</div>';
                                $html .= '</div>';
                                
                                if (!empty($results['details'])) {
                                    $html .= '<div class="mt-4 space-y-1">';
                                    foreach ($results['details'] as $detail) {
                                        $color = match($detail['type'] ?? 'info') {
                                            'error' => 'red',
                                            'warning' => 'yellow',
                                            default => 'blue',
                                        };
                                        $html .= '<div class="text-sm text-' . $color . '-600">';
                                        $html .= '• ' . ($detail['message'] ?? '');
                                        $html .= '</div>';
                                    }
                                    $html .= '</div>';
                                }
                                
                                $html .= '</div>';
                                return $html;
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
                
                Section::make('Version History')
                    ->schema([
                        TextEntry::make('version_history')
                            ->label('')
                            ->html()
                            ->formatStateUsing(function ($record) {
                                $versions = \App\Models\ProjectPcbFile::where('project_id', $record->project_id)
                                    ->orderBy('created_at', 'desc')
                                    ->limit(10)
                                    ->get();
                                
                                $html = '<div class="space-y-2">';
                                foreach ($versions as $version) {
                                    $isCurrentVersion = $version->id === $record->id;
                                    $html .= '<div class="flex items-center justify-between p-2 ' . 
                                             ($isCurrentVersion ? 'bg-primary-50 border border-primary-200' : 'bg-gray-50') . ' rounded">';
                                    $html .= '<div>';
                                    $html .= '<span class="font-medium">' . $version->version . '</span>';
                                    if ($version->is_primary) {
                                        $html .= ' <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded">Primary</span>';
                                    }
                                    if ($version->is_backup) {
                                        $html .= ' <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">Backup</span>';
                                    }
                                    if ($isCurrentVersion) {
                                        $html .= ' <span class="text-xs bg-primary-100 text-primary-700 px-2 py-1 rounded">Current</span>';
                                    }
                                    $html .= '</div>';
                                    $html .= '<div class="text-sm text-gray-500">' . $version->created_at->diffForHumans() . '</div>';
                                    $html .= '</div>';
                                }
                                $html .= '</div>';
                                
                                return $html;
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('download')
                ->label('Download')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function ($record) {
                    return response()->download($record->file_path, $record->filename);
                }),
            Actions\Action::make('view_gerber')
                ->label('View Gerber')
                ->icon('heroicon-o-eye')
                ->visible(fn ($record) => $record->format === 'gerber')
                ->url(fn ($record) => route('filament.admin.gerber-viewer', ['file' => $record->id]))
                ->openUrlInNewTab(),
        ];
    }
}