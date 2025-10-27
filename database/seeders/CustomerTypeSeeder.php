<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'Azienda',
                'description' => 'Cliente aziendale con Partita IVA',
                'active' => true
            ],
            [
                'name' => 'Privato',
                'description' => 'Cliente privato individuale',
                'active' => true
            ],
            [
                'name' => 'Ente Pubblico',
                'description' => 'Ente pubblico o istituzione governativa',
                'active' => true
            ],
        ];

        foreach ($types as $type) {
            \App\Models\CustomerType::create($type);
        }
    }
}
