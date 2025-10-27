<?php

namespace Database\Seeders;

use App\Models\CompanyProfile;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompanyProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CompanyProfile::updateOrCreate(
            ['id' => 1],
            [
                'owner_name' => 'Alessandro Cursoli',
                'owner_title' => 'Amministratore Unico',
                'company_name' => 'Supernova Industries S.R.L.',
                'vat_number' => '08959350722',
                'tax_code' => '08959350722',
                'sdi_code' => 'M5UXCR1',
                'legal_address' => 'Viale Papa Giovanni XXIII 193',
                'legal_city' => 'Bari',
                'legal_postal_code' => '70124',
                'legal_province' => 'BA',
                'legal_country' => 'Italia',
                'claude_model' => 'claude-3-5-sonnet-20241022',
                'claude_enabled' => false,
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'mail_from_name' => 'Supernova Industries S.R.L.',
                'hourly_rate_design' => 50.00,
                'hourly_rate_assembly' => 50.00,
                'pcb_standard_cost' => 200.00,
                'pcb_standard_quantity' => 5,
            ]
        );
    }
}