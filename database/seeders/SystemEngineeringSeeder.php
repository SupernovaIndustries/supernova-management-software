<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemCategory;
use App\Models\SystemPhase;

class SystemEngineeringSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create system phases (fasi del processo)
        $phases = [
            [
                'name' => 'CONCEPT',
                'display_name' => 'Concept & Analisi',
                'description' => 'Fase di analisi dei requisiti e concept design',
                'color' => 'blue',
                'sort_order' => 10,
            ],
            [
                'name' => 'DESIGN',
                'display_name' => 'Progettazione',
                'description' => 'Progettazione del circuito e definizione specifiche',
                'color' => 'purple',
                'sort_order' => 20,
            ],
            [
                'name' => 'LAYOUT',
                'display_name' => 'Layout PCB',
                'description' => 'Progettazione del layout PCB e routing',
                'color' => 'green',
                'sort_order' => 30,
            ],
            [
                'name' => 'VALIDATION',
                'display_name' => 'Validazione',
                'description' => 'Test e validazione del design',
                'color' => 'orange',
                'sort_order' => 40,
            ],
            [
                'name' => 'PRODUCTION',
                'display_name' => 'Produzione',
                'description' => 'Preparazione per produzione e assemblaggio',
                'color' => 'red',
                'sort_order' => 50,
            ],
        ];

        foreach ($phases as $phaseData) {
            SystemPhase::firstOrCreate(
                ['name' => $phaseData['name']],
                $phaseData
            );
        }

        // Create basic system categories (ti aggiungerai le tue)
        $categories = [
            [
                'name' => 'SENSORS',
                'display_name' => 'Sensori',
                'description' => 'Sensori e trasduttori vari',
                'icon' => 'heroicon-o-radio',
                'color' => 'blue',
                'sort_order' => 10,
            ],
            [
                'name' => 'COMMUNICATION',
                'display_name' => 'Comunicazione',
                'description' => 'Moduli di comunicazione wireless e cablata',
                'icon' => 'heroicon-o-signal',
                'color' => 'green',
                'sort_order' => 20,
            ],
            [
                'name' => 'POWER',
                'display_name' => 'Alimentazione',
                'description' => 'Sistemi di alimentazione e power management',
                'icon' => 'heroicon-o-battery-100',
                'color' => 'yellow',
                'sort_order' => 30,
            ],
            [
                'name' => 'CONTROL',
                'display_name' => 'Controllo',
                'description' => 'Microcontrollori e sistemi di controllo',
                'icon' => 'heroicon-o-cpu-chip',
                'color' => 'purple',
                'sort_order' => 40,
            ],
            [
                'name' => 'INTERFACE',
                'display_name' => 'Interfacce',
                'description' => 'Interfacce utente e connettori',
                'icon' => 'heroicon-o-rectangle-stack',
                'color' => 'gray',
                'sort_order' => 50,
            ],
        ];

        foreach ($categories as $categoryData) {
            SystemCategory::firstOrCreate(
                ['name' => $categoryData['name']],
                $categoryData
            );
        }

        $this->command->info('✅ System Engineering base data seeded successfully!');
        $this->command->info('📝 You can now create your custom system variants and checklist templates.');
    }
}