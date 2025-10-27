<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ComplianceStandard;
use App\Models\ComplianceTemplate;

class ComplianceSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Standard di conformità principali
        $ceStandard = ComplianceStandard::firstOrCreate(
            ['code' => 'CE'],
            [
                'name' => 'Conformità Europea',
                'description' => 'Marchio di conformità europeo per prodotti venduti nell\'Area Economica Europea',
                'issuing_authority' => 'Commissione Europea',
                'geographic_scope' => 'EU',
                'applicable_categories' => ['electronics', 'medical', 'automotive', 'industrial'],
                'required_tests' => ['EMC', 'Safety', 'Environmental'],
                'required_documentation' => ['Technical File', 'Declaration of Conformity', 'Risk Assessment'],
                'validity_period' => 'Permanent',
                'renewal_requirements' => 'Aggiornamento in caso di modifiche significative al prodotto',
                'requires_testing' => true,
                'requires_declaration' => true,
                'is_active' => true,
            ]
        );

        $rohsStandard = ComplianceStandard::firstOrCreate(
            ['code' => 'RoHS'],
            [
                'name' => 'Restriction of Hazardous Substances',
                'description' => 'Direttiva europea che limita l\'uso di sostanze pericolose nei prodotti elettronici',
                'issuing_authority' => 'Unione Europea',
                'geographic_scope' => 'EU',
                'applicable_categories' => ['electronics', 'electrical'],
                'required_tests' => ['Chemical Analysis', 'Material Testing'],
                'required_documentation' => ['Material Declaration', 'Test Reports', 'Supply Chain Documentation'],
                'validity_period' => 'Permanent',
                'renewal_requirements' => 'Monitoraggio continuo della supply chain',
                'requires_testing' => true,
                'requires_declaration' => true,
                'is_active' => true,
            ]
        );

        $fccStandard = ComplianceStandard::firstOrCreate(
            ['code' => 'FCC'],
            [
                'name' => 'Federal Communications Commission',
                'description' => 'Certificazione americana per dispositivi che emettono radiofrequenze',
                'issuing_authority' => 'Federal Communications Commission',
                'geographic_scope' => 'USA',
                'applicable_categories' => ['electronics', 'wireless', 'telecommunications'],
                'required_tests' => ['RF', 'EMC', 'SAR'],
                'required_documentation' => ['Equipment Authorization', 'Test Reports', 'User Manual'],
                'validity_period' => 'Permanent',
                'renewal_requirements' => 'Re-certificazione per modifiche hardware significative',
                'requires_testing' => true,
                'requires_declaration' => false,
                'is_active' => true,
            ]
        );

        $iecStandard = ComplianceStandard::firstOrCreate(
            ['code' => 'IEC62368'],
            [
                'name' => 'IEC 62368-1 Safety Standard',
                'description' => 'Standard internazionale di sicurezza per apparecchiature audio/video e ICT',
                'issuing_authority' => 'International Electrotechnical Commission',
                'geographic_scope' => 'Global',
                'applicable_categories' => ['electronics', 'audio', 'video', 'ict'],
                'required_tests' => ['Safety', 'Electrical', 'Mechanical', 'Fire'],
                'required_documentation' => ['Safety Report', 'Test Reports', 'Critical Component List'],
                'validity_period' => '3 years',
                'renewal_requirements' => 'Ri-test completo ogni 3 anni',
                'requires_testing' => true,
                'requires_declaration' => true,
                'is_active' => true,
            ]
        );

        // Template per CE - Dichiarazione di Conformità
        ComplianceTemplate::firstOrCreate(
            ['compliance_standard_id' => $ceStandard->id, 'type' => 'declaration', 'name' => 'Dichiarazione CE Standard'],
            [
                'description' => 'Template standard per dichiarazione di conformità CE',
                'required_fields' => [
                    'manufacturer_name',
                    'manufacturer_address',
                    'product_name',
                    'product_model',
                    'product_description',
                    'harmonized_standards',
                    'notified_body',
                    'declaration_date',
                    'authorized_signature'
                ],
                'template_content' => '
<h1>DICHIARAZIONE DI CONFORMITÀ UE</h1>

<p><strong>Produttore:</strong> {{manufacturer_name}}<br>
<strong>Indirizzo:</strong> {{manufacturer_address}}</p>

<p>Dichiara sotto la propria responsabilità che il prodotto:</p>

<p><strong>Nome prodotto:</strong> {{product_name}}<br>
<strong>Modello:</strong> {{product_model}}<br>
<strong>Descrizione:</strong> {{product_description}}</p>

<p>È conforme alle seguenti direttive e norme armonizzate:</p>
<p>{{harmonized_standards}}</p>

<p><strong>Organismo notificato:</strong> {{notified_body}}</p>

<p><strong>Data:</strong> {{declaration_date}}<br>
<strong>Firma autorizzata:</strong> {{authorized_signature}}</p>
                ',
                'ai_prompts' => [
                    'default' => 'Genera una dichiarazione di conformità CE professionale basata sui dati del progetto. Includi tutti i riferimenti normativi appropriati e assicurati che il linguaggio sia formalmente corretto secondo la legislazione europea.',
                    'technical' => 'Analizza le specifiche tecniche del progetto e determina automaticamente le direttive CE applicabili e le norme armonizzate pertinenti.'
                ],
                'output_format' => 'pdf',
                'requires_ai_assistance' => true,
                'is_default' => true,
                'is_active' => true,
            ]
        );

        // Template per RoHS - Dichiarazione Materiali
        ComplianceTemplate::firstOrCreate(
            ['compliance_standard_id' => $rohsStandard->id, 'type' => 'declaration', 'name' => 'Dichiarazione RoHS Standard'],
            [
                'description' => 'Template per dichiarazione di conformità RoHS',
                'required_fields' => [
                    'manufacturer_name',
                    'product_name',
                    'product_model',
                    'material_composition',
                    'restricted_substances',
                    'test_reports',
                    'declaration_date'
                ],
                'template_content' => '
<h1>DICHIARAZIONE DI CONFORMITÀ RoHS</h1>

<p><strong>Produttore:</strong> {{manufacturer_name}}</p>

<p>Dichiara che il prodotto:</p>
<p><strong>{{product_name}} - {{product_model}}</strong></p>

<p>È conforme alla Direttiva RoHS 2011/65/UE e successive modifiche.</p>

<p><strong>Composizione materiali:</strong><br>
{{material_composition}}</p>

<p><strong>Sostanze ristrette verificate:</strong><br>
{{restricted_substances}}</p>

<p><strong>Report di test:</strong><br>
{{test_reports}}</p>

<p><strong>Data dichiarazione:</strong> {{declaration_date}}</p>
                ',
                'ai_prompts' => [
                    'default' => 'Genera una dichiarazione RoHS basata sui componenti del progetto. Analizza la BOM per identificare potenziali sostanze ristrette e suggerisci test necessari.',
                ],
                'output_format' => 'pdf',
                'requires_ai_assistance' => true,
                'is_default' => true,
                'is_active' => true,
            ]
        );

        // Template per FCC - Equipment Authorization
        ComplianceTemplate::firstOrCreate(
            ['compliance_standard_id' => $fccStandard->id, 'type' => 'certificate', 'name' => 'FCC Equipment Authorization'],
            [
                'description' => 'Template per certificazione FCC',
                'required_fields' => [
                    'applicant_name',
                    'device_name',
                    'fcc_id',
                    'frequency_range',
                    'power_output',
                    'test_lab',
                    'test_report_number',
                    'grant_date'
                ],
                'template_content' => '
<h1>FCC EQUIPMENT AUTHORIZATION</h1>

<p><strong>Applicant:</strong> {{applicant_name}}</p>
<p><strong>Device:</strong> {{device_name}}</p>
<p><strong>FCC ID:</strong> {{fcc_id}}</p>

<p><strong>Technical Specifications:</strong><br>
Frequency Range: {{frequency_range}}<br>
Power Output: {{power_output}}</p>

<p><strong>Test Laboratory:</strong> {{test_lab}}<br>
<strong>Test Report:</strong> {{test_report_number}}</p>

<p><strong>Grant Date:</strong> {{grant_date}}</p>

<p>This device complies with Part 15 of the FCC Rules.</p>
                ',
                'ai_prompts' => [
                    'default' => 'Genera documentazione FCC basata sulle specifiche RF del progetto. Identifica automaticamente le parti FCC applicabili e i requisiti di test.',
                ],
                'output_format' => 'pdf',
                'requires_ai_assistance' => true,
                'is_default' => true,
                'is_active' => true,
            ]
        );

        // Template tecnico per IEC
        ComplianceTemplate::firstOrCreate(
            ['compliance_standard_id' => $iecStandard->id, 'type' => 'technical_file', 'name' => 'IEC 62368 Technical File'],
            [
                'description' => 'File tecnico per standard IEC 62368-1',
                'required_fields' => [
                    'product_description',
                    'circuit_diagrams',
                    'safety_analysis',
                    'component_specifications',
                    'test_results',
                    'risk_assessment'
                ],
                'template_content' => '
<h1>TECHNICAL FILE - IEC 62368-1</h1>

<h2>1. Product Description</h2>
<p>{{product_description}}</p>

<h2>2. Circuit Diagrams</h2>
<p>{{circuit_diagrams}}</p>

<h2>3. Safety Analysis</h2>
<p>{{safety_analysis}}</p>

<h2>4. Component Specifications</h2>
<p>{{component_specifications}}</p>

<h2>5. Test Results</h2>
<p>{{test_results}}</p>

<h2>6. Risk Assessment</h2>
<p>{{risk_assessment}}</p>
                ',
                'ai_prompts' => [
                    'default' => 'Genera un file tecnico IEC 62368-1 completo analizzando le specifiche del progetto. Includi analisi di sicurezza dettagliata e valutazione dei rischi.',
                    'safety' => 'Analizza i circuiti e componenti per identificare potenziali rischi di sicurezza secondo IEC 62368-1.',
                ],
                'output_format' => 'pdf',
                'requires_ai_assistance' => true,
                'is_default' => true,
                'is_active' => true,
            ]
        );

        $this->command->info('✅ Compliance system seeded successfully!');
        $this->command->info('📋 Created standards: CE, RoHS, FCC, IEC62368');
        $this->command->info('📄 Created 5 compliance templates with AI integration');
    }
}