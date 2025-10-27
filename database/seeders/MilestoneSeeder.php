<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MilestoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $milestones = [
            [
                'name' => 'Kickoff Meeting',
                'description' => 'Riunione iniziale di progetto',
                'icon' => 'heroicon-o-play',
                'color' => 'primary',
                'is_active' => true
            ],
            [
                'name' => 'Requirements Analysis',
                'description' => 'Analisi e definizione requisiti',
                'icon' => 'heroicon-o-document-text',
                'color' => 'blue',
                'is_active' => true
            ],
            [
                'name' => 'Schematic Design',
                'description' => 'Progettazione schema elettrico',
                'icon' => 'heroicon-o-pencil-square',
                'color' => 'yellow',
                'is_active' => true
            ],
            [
                'name' => 'PCB Layout',
                'description' => 'Layout del circuito stampato',
                'icon' => 'heroicon-o-cpu-chip',
                'color' => 'green',
                'is_active' => true
            ],
            [
                'name' => 'Prototype Assembly',
                'description' => 'Assemblaggio prototipo',
                'icon' => 'heroicon-o-wrench-screwdriver',
                'color' => 'orange',
                'is_active' => true
            ],
            [
                'name' => 'Testing & Validation',
                'description' => 'Test e validazione',
                'icon' => 'heroicon-o-beaker',
                'color' => 'purple',
                'is_active' => true
            ],
            [
                'name' => 'Production Ready',
                'description' => 'Pronto per produzione',
                'icon' => 'heroicon-o-check-circle',
                'color' => 'success',
                'is_active' => true
            ],
            [
                'name' => 'Documentation',
                'description' => 'Documentazione tecnica',
                'icon' => 'heroicon-o-book-open',
                'color' => 'gray',
                'is_active' => true
            ],
        ];

        foreach ($milestones as $milestone) {
            \App\Models\Milestone::create($milestone);
        }
    }
}
