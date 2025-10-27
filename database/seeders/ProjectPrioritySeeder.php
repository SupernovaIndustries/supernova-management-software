<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProjectPrioritySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $priorities = [
            [
                'name' => 'Bassa',
                'color' => 'gray',
                'sort_order' => 1,
                'is_active' => true
            ],
            [
                'name' => 'Media',
                'color' => 'yellow',
                'sort_order' => 2,
                'is_active' => true
            ],
            [
                'name' => 'Alta',
                'color' => 'orange',
                'sort_order' => 3,
                'is_active' => true
            ],
            [
                'name' => 'Urgente',
                'color' => 'red',
                'sort_order' => 4,
                'is_active' => true
            ],
        ];

        foreach ($priorities as $priority) {
            \App\Models\ProjectPriority::create($priority);
        }
    }
}
