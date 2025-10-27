<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProjectProgressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $progressStates = [
            [
                'name' => 'Non Iniziato',
                'percentage' => 0,
                'color' => 'gray',
                'sort_order' => 1,
                'is_active' => true
            ],
            [
                'name' => 'In Pianificazione',
                'percentage' => 10,
                'color' => 'blue',
                'sort_order' => 2,
                'is_active' => true
            ],
            [
                'name' => 'In Progresso',
                'percentage' => 50,
                'color' => 'yellow',
                'sort_order' => 3,
                'is_active' => true
            ],
            [
                'name' => 'In Test',
                'percentage' => 80,
                'color' => 'orange',
                'sort_order' => 4,
                'is_active' => true
            ],
            [
                'name' => 'Completato',
                'percentage' => 100,
                'color' => 'green',
                'sort_order' => 5,
                'is_active' => true
            ],
            [
                'name' => 'In Pausa',
                'percentage' => 25,
                'color' => 'gray',
                'sort_order' => 6,
                'is_active' => true
            ],
        ];

        foreach ($progressStates as $progress) {
            \App\Models\ProjectProgress::create($progress);
        }
    }
}
