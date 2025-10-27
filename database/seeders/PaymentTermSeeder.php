<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentTermSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $terms = [
            [
                'name' => 'Net 30',
                'description' => 'Pagamento entro 30 giorni',
                'days' => 30,
                'discount_percentage' => 0,
                'discount_days' => 0,
                'active' => true
            ],
            [
                'name' => 'Net 60',
                'description' => 'Pagamento entro 60 giorni',
                'days' => 60,
                'discount_percentage' => 0,
                'discount_days' => 0,
                'active' => true
            ],
            [
                'name' => '2/10 Net 30',
                'description' => '2% sconto se pagato entro 10 giorni, altrimenti netto 30',
                'days' => 30,
                'discount_percentage' => 2.00,
                'discount_days' => 10,
                'active' => true
            ],
            [
                'name' => 'Pagamento Immediato',
                'description' => 'Pagamento alla consegna o subito',
                'days' => 0,
                'discount_percentage' => 0,
                'discount_days' => 0,
                'active' => true
            ],
        ];

        foreach ($terms as $term) {
            \App\Models\PaymentTerm::create($term);
        }
    }
}
