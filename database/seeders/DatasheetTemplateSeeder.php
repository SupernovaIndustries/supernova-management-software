<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DatasheetTemplate;

class DatasheetTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Template predefinito per progetti
        DatasheetTemplate::firstOrCreate(
            ['name' => 'Template Progetto Standard', 'type' => 'project'],
            [
                'description' => 'Template predefinito per la generazione di datasheet di progetti elettronici',
                'sections' => [
                    ['name' => 'overview', 'title' => 'Panoramica Progetto', 'enabled' => true, 'sort_order' => 1],
                    ['name' => 'specifications', 'title' => 'Specifiche Tecniche', 'enabled' => true, 'sort_order' => 2],
                    ['name' => 'features', 'title' => 'Caratteristiche e Funzionalità', 'enabled' => true, 'sort_order' => 3],
                    ['name' => 'bom', 'title' => 'Bill of Materials', 'enabled' => true, 'sort_order' => 4],
                    ['name' => 'performance', 'title' => 'Performance e Test', 'enabled' => false, 'sort_order' => 5],
                    ['name' => 'compliance', 'title' => 'Conformità e Certificazioni', 'enabled' => false, 'sort_order' => 6],
                ],
                'styles' => [
                    'primary-color' => '#007acc',
                    'secondary-color' => '#f8f9fa',
                    'font-family' => 'Arial, sans-serif',
                ],
                'include_company_info' => true,
                'include_toc' => true,
                'output_format' => 'pdf',
                'is_default' => true,
                'is_active' => true,
            ]
        );

        // Template predefinito per componenti
        DatasheetTemplate::firstOrCreate(
            ['name' => 'Template Componente Standard', 'type' => 'component'],
            [
                'description' => 'Template predefinito per la generazione di datasheet di componenti elettronici',
                'sections' => [
                    ['name' => 'overview', 'title' => 'Panoramica Componente', 'enabled' => true, 'sort_order' => 1],
                    ['name' => 'electrical_specs', 'title' => 'Specifiche Elettriche', 'enabled' => true, 'sort_order' => 2],
                    ['name' => 'mechanical_specs', 'title' => 'Specifiche Meccaniche', 'enabled' => true, 'sort_order' => 3],
                    ['name' => 'environmental_specs', 'title' => 'Specifiche Ambientali', 'enabled' => true, 'sort_order' => 4],
                    ['name' => 'pin_configuration', 'title' => 'Configurazione Pin', 'enabled' => false, 'sort_order' => 5],
                    ['name' => 'application_notes', 'title' => 'Note Applicative', 'enabled' => false, 'sort_order' => 6],
                ],
                'styles' => [
                    'primary-color' => '#28a745',
                    'secondary-color' => '#f8f9fa',
                    'font-family' => 'Arial, sans-serif',
                ],
                'include_company_info' => true,
                'include_toc' => false,
                'output_format' => 'pdf',
                'is_default' => true,
                'is_active' => true,
            ]
        );

        // Template predefinito per sistemi
        DatasheetTemplate::firstOrCreate(
            ['name' => 'Template Sistema Standard', 'type' => 'system'],
            [
                'description' => 'Template predefinito per la generazione di datasheet di sistemi complessi',
                'sections' => [
                    ['name' => 'overview', 'title' => 'Panoramica Sistema', 'enabled' => true, 'sort_order' => 1],
                    ['name' => 'architecture', 'title' => 'Architettura', 'enabled' => true, 'sort_order' => 2],
                    ['name' => 'interfaces', 'title' => 'Interfacce', 'enabled' => true, 'sort_order' => 3],
                    ['name' => 'performance', 'title' => 'Performance', 'enabled' => true, 'sort_order' => 4],
                    ['name' => 'integration', 'title' => 'Note Integrazione', 'enabled' => false, 'sort_order' => 5],
                ],
                'styles' => [
                    'primary-color' => '#6f42c1',
                    'secondary-color' => '#f8f9fa',
                    'font-family' => 'Arial, sans-serif',
                ],
                'include_company_info' => true,
                'include_toc' => true,
                'output_format' => 'pdf',
                'is_default' => true,
                'is_active' => true,
            ]
        );

        // Template compatto per progetti
        DatasheetTemplate::firstOrCreate(
            ['name' => 'Template Progetto Compatto', 'type' => 'project'],
            [
                'description' => 'Template compatto per datasheet di progetti - ideale per presentazioni rapide',
                'sections' => [
                    ['name' => 'overview', 'title' => 'Panoramica', 'enabled' => true, 'sort_order' => 1],
                    ['name' => 'features', 'title' => 'Caratteristiche Principali', 'enabled' => true, 'sort_order' => 2],
                    ['name' => 'specifications', 'title' => 'Specifiche', 'enabled' => true, 'sort_order' => 3],
                ],
                'styles' => [
                    'primary-color' => '#17a2b8',
                    'secondary-color' => '#f8f9fa',
                    'font-family' => 'Arial, sans-serif',
                ],
                'include_company_info' => true,
                'include_toc' => false,
                'output_format' => 'pdf',
                'is_default' => false,
                'is_active' => true,
            ]
        );

        $this->command->info('✅ Datasheet templates seeded successfully!');
        $this->command->info('📋 4 template predefiniti creati: Standard Project, Standard Component, Standard System, Compact Project');
    }
}