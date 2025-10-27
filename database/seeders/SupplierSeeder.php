<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'Mouser Electronics',
                'code' => 'MOUSER',
                'api_name' => 'mouser',
                'website' => 'https://www.mouser.it',
                'email' => 'vendite@mouser.com',
                'phone' => '+39 02 575 065 71',
                'vat_number' => 'IT12345678901',
                'api_enabled' => true,
                'api_settings' => [
                    'base_url' => 'https://api.mouser.com/api/v1',
                    'timeout' => 30,
                ],
            ],
            [
                'name' => 'Digi-Key Electronics',
                'code' => 'DIGIKEY',
                'api_name' => 'digikey',
                'website' => 'https://www.digikey.it',
                'email' => 'sales@digikey.com',
                'phone' => '+39 02 8735 6801',
                'vat_number' => 'IT98765432109',
                'api_enabled' => true,
                'api_settings' => [
                    'base_url' => 'https://api.digikey.com/v1',
                    'timeout' => 30,
                ],
            ],
            [
                'name' => 'RS Components',
                'code' => 'RS',
                'api_name' => 'rs',
                'website' => 'https://it.rs-online.com',
                'email' => 'vendite@rs-components.com',
                'phone' => '+39 02 660 321',
                'vat_number' => 'IT11223344556',
                'api_enabled' => false,
            ],
            [
                'name' => 'Farnell',
                'code' => 'FARNELL',
                'api_name' => null,
                'website' => 'https://it.farnell.com',
                'email' => 'sales@farnell.com',
                'phone' => '+39 02 939 951',
                'vat_number' => 'IT55443322110',
                'api_enabled' => false,
            ],
            [
                'name' => 'TME Electronics',
                'code' => 'TME',
                'api_name' => null,
                'website' => 'https://www.tme.eu',
                'email' => 'vendite@tme.eu',
                'phone' => '+39 0474 553 180',
                'vat_number' => 'IT66778899001',
                'api_enabled' => false,
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier);
        }
    }
}