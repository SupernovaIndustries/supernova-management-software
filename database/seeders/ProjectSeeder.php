<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::first();
        $customers = Customer::all();

        $projects = [
            [
                'code' => 'PRJ-2024-001',
                'name' => 'Sistema di Controllo Temperatura',
                'description' => 'Sviluppo sistema IoT per monitoraggio e controllo temperatura in tempo reale',
                'customer_id' => $customers->where('code', 'CLI001')->first()->id,
                'user_id' => $admin->id,
                'status' => 'in_progress',
                'priority' => 'high',
                'start_date' => now()->subDays(30),
                'due_date' => now()->addDays(30),
                'budget' => 15000,
                'actual_cost' => 8500,
                'progress' => 65,
                'milestones' => [
                    ['name' => 'Design PCB', 'completed' => true],
                    ['name' => 'Prototipo', 'completed' => true],
                    ['name' => 'Testing', 'completed' => false],
                    ['name' => 'Produzione', 'completed' => false],
                ],
                'syncthing_folder' => 'DPThermofluid/PRJ-2024-001',
            ],
            [
                'code' => 'PRJ-2024-002',
                'name' => 'Automazione Domotica',
                'description' => 'Sistema di automazione per controllo luci e tapparelle',
                'customer_id' => $customers->where('code', 'CLI002')->first()->id,
                'user_id' => $admin->id,
                'status' => 'planning',
                'priority' => 'medium',
                'start_date' => now()->addDays(7),
                'due_date' => now()->addDays(60),
                'budget' => 5000,
                'progress' => 10,
                'syncthing_folder' => 'Giuseppe Dipierro/PRJ-2024-002',
            ],
            [
                'code' => 'PRJ-2024-003',
                'name' => 'Sensore Pressione Industriale',
                'description' => 'Sviluppo sensore di pressione ad alta precisione per applicazioni industriali',
                'customer_id' => $customers->where('code', 'CLI003')->first()->id,
                'user_id' => $admin->id,
                'status' => 'in_progress',
                'priority' => 'urgent',
                'start_date' => now()->subDays(45),
                'due_date' => now()->subDays(5), // Overdue
                'budget' => 25000,
                'actual_cost' => 28000, // Over budget
                'progress' => 85,
                'syncthing_folder' => 'MAV/PRJ-2024-003',
            ],
            [
                'code' => 'PRJ-2024-004',
                'name' => 'Gateway LoRaWAN',
                'description' => 'Gateway multi-canale per rete LoRaWAN',
                'customer_id' => $customers->where('code', 'CLI004')->first()->id,
                'user_id' => $admin->id,
                'status' => 'completed',
                'priority' => 'high',
                'start_date' => now()->subDays(90),
                'due_date' => now()->subDays(10),
                'completed_date' => now()->subDays(12),
                'budget' => 12000,
                'actual_cost' => 11500,
                'progress' => 100,
                'syncthing_folder' => 'Switchup/PRJ-2024-004',
            ],
            [
                'code' => 'PRJ-2024-005',
                'name' => 'Display Touch Personalizzato',
                'description' => 'Interfaccia utente con display touch 7" per sistema embedded',
                'customer_id' => $customers->where('code', 'CLI005')->first()->id,
                'user_id' => $admin->id,
                'status' => 'in_progress',
                'priority' => 'low',
                'start_date' => now()->subDays(15),
                'due_date' => now()->addDays(75),
                'budget' => 8000,
                'actual_cost' => 2500,
                'progress' => 30,
                'syncthing_folder' => 'Relpy/PRJ-2024-005',
            ],
        ];

        foreach ($projects as $project) {
            Project::create($project);
        }
    }
}