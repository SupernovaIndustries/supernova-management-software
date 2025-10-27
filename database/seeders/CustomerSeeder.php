<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = [
            [
                'code' => 'CLI001',
                'type' => 'company',
                'name' => 'Mario Rossi',
                'company_name' => 'DPThermofluid S.r.l.',
                'vat_number' => 'IT12345678901',
                'tax_code' => 'DPTHFL12A01H501Z',
                'email' => 'info@dpthermofluid.it',
                'phone' => '+39 02 1234567',
                'mobile' => '+39 333 1234567',
                'address' => 'Via dell\'Industria, 123',
                'city' => 'Milano',
                'postal_code' => '20100',
                'province' => 'MI',
                'payment_terms' => '30 giorni',
                'credit_limit' => 10000,
                'syncthing_folder' => 'DPThermofluid',
            ],
            [
                'code' => 'CLI002',
                'type' => 'individual',
                'name' => 'Giuseppe Dipierro',
                'email' => 'giuseppe.dipierro@email.com',
                'mobile' => '+39 340 9876543',
                'address' => 'Via Roma, 45',
                'city' => 'Bari',
                'postal_code' => '70100',
                'province' => 'BA',
                'payment_terms' => 'Immediato',
                'syncthing_folder' => 'Giuseppe Dipierro',
            ],
            [
                'code' => 'CLI003',
                'type' => 'company',
                'name' => 'Laura Bianchi',
                'company_name' => 'MAV Engineering',
                'vat_number' => 'IT98765432109',
                'email' => 'info@mavengineering.it',
                'phone' => '+39 06 9876543',
                'address' => 'Via dei Progetti, 78',
                'city' => 'Roma',
                'postal_code' => '00100',
                'province' => 'RM',
                'payment_terms' => '60 giorni',
                'credit_limit' => 25000,
                'syncthing_folder' => 'MAV',
            ],
            [
                'code' => 'CLI004',
                'type' => 'company',
                'name' => 'Marco Verdi',
                'company_name' => 'Switchup Solutions',
                'vat_number' => 'IT11223344556',
                'email' => 'marco@switchup.com',
                'phone' => '+39 055 5678901',
                'address' => 'Viale della Tecnologia, 234',
                'city' => 'Firenze',
                'postal_code' => '50100',
                'province' => 'FI',
                'payment_terms' => '45 giorni',
                'credit_limit' => 15000,
                'syncthing_folder' => 'Switchup',
            ],
            [
                'code' => 'CLI005',
                'type' => 'company',
                'name' => 'Anna Neri',
                'company_name' => 'Relpy Tech',
                'vat_number' => 'IT66778899001',
                'email' => 'info@relpy.tech',
                'phone' => '+39 011 2345678',
                'address' => 'Corso Innovazione, 567',
                'city' => 'Torino',
                'postal_code' => '10100',
                'province' => 'TO',
                'payment_terms' => '30 giorni',
                'credit_limit' => 8000,
                'syncthing_folder' => 'Relpy',
            ],
        ];

        foreach ($customers as $customer) {
            Customer::create($customer);
        }
    }
}