<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\SupplierCsvMapping;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SupplierCsvMappingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crea o trova il fornitore Mouser
        $mouser = Supplier::firstOrCreate(
            ['code' => 'MOUSER'],
            [
                'name' => 'Mouser Electronics',
                'api_name' => 'mouser',
                'website' => 'https://www.mouser.it',
                'email' => 'orders@mouser.com',
                'is_active' => true,
                'api_enabled' => false,
            ]
        );

        // Crea o trova il fornitore DigiKey
        $digikey = Supplier::firstOrCreate(
            ['code' => 'DIGIKEY'],
            [
                'name' => 'DigiKey Electronics',
                'api_name' => 'digikey',
                'website' => 'https://www.digikey.it',
                'email' => 'orders@digikey.com',
                'is_active' => true,
                'api_enabled' => false,
            ]
        );

        // Mapping CSV per Mouser
        // Formato: "Sales Order No: Web Order No: PO Number: Line No. Order Date: Order Status: Mouser No: Mfr. No: Desc.: Customer No Order Qty. Ext. (EUR) Price (EUR) Status Date Invoice No. Tariff: Price (EUR) Tariff: Ext. (EUR)"
        $mouserMappings = [
            [
                'field_name' => 'sku',
                'csv_column_name' => 'Mouser No:',
                'data_type' => 'string',
                'is_required' => true,
            ],
            [
                'field_name' => 'manufacturer_part_number',
                'csv_column_name' => 'Mfr. No:',
                'data_type' => 'string',
                'is_required' => true,
            ],
            [
                'field_name' => 'description',
                'csv_column_name' => 'Desc.:',
                'data_type' => 'string',
                'is_required' => false,
            ],
            [
                'field_name' => 'unit_price',
                'csv_column_name' => 'Price (EUR)',
                'data_type' => 'decimal',
                'is_required' => true,
            ],
            [
                'field_name' => 'stock_quantity',
                'csv_column_name' => 'Order Qty.',
                'data_type' => 'integer',
                'is_required' => false,
            ],
            [
                'field_name' => 'purchase_date',
                'csv_column_name' => 'Order Date:',
                'data_type' => 'date',
                'is_required' => false,
            ],
            [
                'field_name' => 'invoice_reference',
                'csv_column_name' => 'Invoice No.',
                'data_type' => 'string',
                'is_required' => false,
            ],
            [
                'field_name' => 'currency',
                'csv_column_name' => '',
                'data_type' => 'string',
                'default_value' => 'EUR',
                'is_required' => false,
            ],
            [
                'field_name' => 'supplier',
                'csv_column_name' => '',
                'data_type' => 'string',
                'default_value' => 'Mouser',
                'is_required' => false,
            ],
        ];

        // Mapping CSV per DigiKey
        // Formato: "Index,DigiKey Part #,Manufacturer Part Number,Manufacturer,Description,Customer Reference,Quantity,Backorder,Unit Price,Extended Price"
        $digikeyMappings = [
            [
                'field_name' => 'sku',
                'csv_column_name' => 'DigiKey Part #',
                'data_type' => 'string',
                'is_required' => true,
            ],
            [
                'field_name' => 'manufacturer_part_number',
                'csv_column_name' => 'Manufacturer Part Number',
                'data_type' => 'string',
                'is_required' => true,
            ],
            [
                'field_name' => 'manufacturer',
                'csv_column_name' => 'Manufacturer',
                'data_type' => 'string',
                'is_required' => false,
            ],
            [
                'field_name' => 'description',
                'csv_column_name' => 'Description',
                'data_type' => 'string',
                'is_required' => false,
            ],
            [
                'field_name' => 'unit_price',
                'csv_column_name' => 'Unit Price',
                'data_type' => 'decimal',
                'is_required' => true,
            ],
            [
                'field_name' => 'stock_quantity',
                'csv_column_name' => 'Quantity',
                'data_type' => 'integer',
                'is_required' => false,
            ],
            [
                'field_name' => 'currency',
                'csv_column_name' => '',
                'data_type' => 'string',
                'default_value' => 'EUR',
                'is_required' => false,
            ],
            [
                'field_name' => 'supplier',
                'csv_column_name' => '',
                'data_type' => 'string',
                'default_value' => 'DigiKey',
                'is_required' => false,
            ],
        ];

        // Elimina mappings esistenti per evitare duplicati
        SupplierCsvMapping::where('supplier_id', $mouser->id)->delete();
        SupplierCsvMapping::where('supplier_id', $digikey->id)->delete();

        // Crea i mapping per Mouser
        foreach ($mouserMappings as $mapping) {
            SupplierCsvMapping::create([
                'supplier_id' => $mouser->id,
                'field_name' => $mapping['field_name'],
                'csv_column_name' => $mapping['csv_column_name'],
                'data_type' => $mapping['data_type'],
                'default_value' => $mapping['default_value'] ?? null,
                'is_required' => $mapping['is_required'],
                'is_active' => true,
            ]);
        }

        // Crea i mapping per DigiKey
        foreach ($digikeyMappings as $mapping) {
            SupplierCsvMapping::create([
                'supplier_id' => $digikey->id,
                'field_name' => $mapping['field_name'],
                'csv_column_name' => $mapping['csv_column_name'],
                'data_type' => $mapping['data_type'],
                'default_value' => $mapping['default_value'] ?? null,
                'is_required' => $mapping['is_required'],
                'is_active' => true,
            ]);
        }

        $this->command->info('✅ Creati fornitori e mapping CSV per Mouser e DigiKey');
        $this->command->info("📊 Mouser: {$mouser->csvMappings()->count()} mapping creati");
        $this->command->info("📊 DigiKey: {$digikey->csvMappings()->count()} mapping creati");
    }
}
