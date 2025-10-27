<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Component;
use Illuminate\Database\Seeder;

class ComponentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get categories
        $resistenzeSmd = Category::where('slug', 'resistenze-smd')->first();
        $condensatoriCeramici = Category::where('slug', 'condensatori-ceramici')->first();
        $microcontrollori = Category::where('slug', 'microcontrollori')->first();
        $regulators = Category::where('slug', 'regolatori-tensione')->first();
        
        $components = [
            // Resistenze SMD
            [
                'sku' => 'RES-0805-10K',
                'manufacturer_part_number' => 'RC0805FR-0710KL',
                'name' => 'Resistenza SMD 10kΩ 0805 1%',
                'description' => 'Resistenza SMD 10kΩ ±1% 0.125W 0805',
                'category_id' => $resistenzeSmd->id,
                'manufacturer' => 'Yageo',
                'package' => '0805',
                'specifications' => [
                    'resistance' => '10kΩ',
                    'tolerance' => '1%',
                    'power' => '0.125W',
                    'voltage' => '150V',
                ],
                'unit_price' => 0.02,
                'stock_quantity' => 500,
                'min_stock_level' => 100,
                'reorder_quantity' => 1000,
                'storage_location' => 'A1-01',
            ],
            [
                'sku' => 'RES-0805-1K',
                'manufacturer_part_number' => 'RC0805FR-071KL',
                'name' => 'Resistenza SMD 1kΩ 0805 1%',
                'description' => 'Resistenza SMD 1kΩ ±1% 0.125W 0805',
                'category_id' => $resistenzeSmd->id,
                'manufacturer' => 'Yageo',
                'package' => '0805',
                'specifications' => [
                    'resistance' => '1kΩ',
                    'tolerance' => '1%',
                    'power' => '0.125W',
                    'voltage' => '150V',
                ],
                'unit_price' => 0.02,
                'stock_quantity' => 50, // Low stock
                'min_stock_level' => 100,
                'reorder_quantity' => 1000,
                'storage_location' => 'A1-02',
            ],
            
            // Condensatori
            [
                'sku' => 'CAP-0805-100NF',
                'manufacturer_part_number' => 'CL21B104KBCNNNC',
                'name' => 'Condensatore Ceramico 100nF 50V 0805',
                'description' => 'Condensatore ceramico X7R 100nF 50V 0805',
                'category_id' => $condensatoriCeramici->id,
                'manufacturer' => 'Samsung',
                'package' => '0805',
                'specifications' => [
                    'capacitance' => '100nF',
                    'voltage' => '50V',
                    'dielectric' => 'X7R',
                    'tolerance' => '10%',
                ],
                'unit_price' => 0.05,
                'stock_quantity' => 300,
                'min_stock_level' => 100,
                'reorder_quantity' => 500,
                'storage_location' => 'A2-01',
            ],
            
            // Microcontrollori
            [
                'sku' => 'MCU-STM32F103',
                'manufacturer_part_number' => 'STM32F103C8T6',
                'name' => 'STM32F103C8T6 ARM Cortex-M3',
                'description' => 'Microcontrollore ARM Cortex-M3 72MHz 64KB Flash',
                'category_id' => $microcontrollori->id,
                'manufacturer' => 'STMicroelectronics',
                'package' => 'LQFP-48',
                'specifications' => [
                    'core' => 'ARM Cortex-M3',
                    'frequency' => '72MHz',
                    'flash' => '64KB',
                    'ram' => '20KB',
                    'voltage' => '2.0-3.6V',
                ],
                'unit_price' => 2.50,
                'stock_quantity' => 25,
                'min_stock_level' => 10,
                'reorder_quantity' => 50,
                'storage_location' => 'B1-01',
                'datasheet_url' => 'https://www.st.com/resource/en/datasheet/stm32f103c8.pdf',
            ],
            [
                'sku' => 'MCU-ESP32',
                'manufacturer_part_number' => 'ESP32-WROOM-32',
                'name' => 'ESP32-WROOM-32 WiFi+BT Module',
                'description' => 'Modulo WiFi e Bluetooth dual-core',
                'category_id' => $microcontrollori->id,
                'manufacturer' => 'Espressif',
                'package' => 'Module',
                'specifications' => [
                    'core' => 'Dual-core Xtensa 32-bit',
                    'frequency' => '240MHz',
                    'flash' => '4MB',
                    'wifi' => '802.11 b/g/n',
                    'bluetooth' => 'v4.2 BR/EDR and BLE',
                ],
                'unit_price' => 3.80,
                'stock_quantity' => 5, // Low stock
                'min_stock_level' => 20,
                'reorder_quantity' => 50,
                'storage_location' => 'B1-02',
            ],
            
            // Regolatori
            [
                'sku' => 'REG-LM7805',
                'manufacturer_part_number' => 'LM7805CT',
                'name' => 'LM7805 Regolatore 5V 1A TO-220',
                'description' => 'Regolatore di tensione lineare 5V 1A',
                'category_id' => $regulators->id,
                'manufacturer' => 'Texas Instruments',
                'package' => 'TO-220',
                'specifications' => [
                    'output_voltage' => '5V',
                    'max_current' => '1A',
                    'dropout' => '2V',
                    'input_voltage' => '7-35V',
                ],
                'unit_price' => 0.45,
                'stock_quantity' => 80,
                'min_stock_level' => 50,
                'reorder_quantity' => 100,
                'storage_location' => 'C1-01',
            ],
        ];

        foreach ($components as $component) {
            Component::create($component);
        }
    }
}