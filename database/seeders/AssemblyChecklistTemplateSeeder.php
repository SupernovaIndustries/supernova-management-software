<?php

namespace Database\Seeders;

use App\Models\AssemblyChecklistTemplate;
use Illuminate\Database\Seeder;

class AssemblyChecklistTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get first admin user or create system user
        $systemUser = \App\Models\User::first();

        if (!$systemUser) {
            $this->command->error('No users found. Please create a user first.');
            return;
        }

        $this->createPrototypeTemplate($systemUser->id);
        $this->createProductionTemplate($systemUser->id);
        $this->createGenericTemplate($systemUser->id);
    }

    /**
     * Create prototype assembly template.
     */
    private function createPrototypeTemplate(int $userId): void
    {
        $template = AssemblyChecklistTemplate::create([
            'name' => 'Prototipo PCB Standard',
            'description' => 'Template per assemblaggio schede prototipo/test',
            'board_type' => 'prototype',
            'complexity_level' => 'medium',
            'is_default' => true,
            'is_active' => true,
            'created_by' => $userId,
            'metadata' => [
                'created_by' => 'system',
                'version' => '1.0',
                'standards' => ['IPC-A-610'],
            ],
        ]);

        $items = [
            // Pre-Assembly
            [
                'title' => 'Verifica ESD',
                'description' => 'Assicurarsi che la postazione di lavoro sia protetta da scariche elettrostatiche (ESD)',
                'category' => 'Pre-Assembly',
                'type' => 'checkbox',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 1,
            ],
            [
                'title' => 'Verifica PCB',
                'description' => 'Ispezionare il PCB per danni visibili, tracce interrotte o delaminazione',
                'category' => 'Pre-Assembly',
                'type' => 'photo',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 2,
            ],
            [
                'title' => 'Pulizia PCB',
                'description' => 'Pulire il PCB con alcool isopropilico se necessario',
                'category' => 'Pre-Assembly',
                'type' => 'checkbox',
                'is_critical' => false,
                'is_required' => false,
                'sort_order' => 3,
            ],

            // Component Inspection
            [
                'title' => 'Verifica Componenti',
                'description' => 'Verificare che tutti i componenti siano disponibili e corrispondano alla BOM',
                'category' => 'Component Inspection',
                'type' => 'checkbox',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 4,
            ],
            [
                'title' => 'Controllo Orientamento',
                'description' => 'Verificare l\'orientamento corretto di componenti polarizzati (LED, diodi, IC)',
                'category' => 'Component Inspection',
                'type' => 'checkbox',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 5,
            ],
            [
                'title' => 'Controllo Valori',
                'description' => 'Verificare i valori dei componenti passivi (resistenze, condensatori)',
                'category' => 'Component Inspection',
                'type' => 'measurement',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 6,
            ],

            // Soldering
            [
                'title' => 'Impostazione Saldatore',
                'description' => 'Impostare la temperatura del saldatore secondo specifiche (tipicamente 320-350°C)',
                'category' => 'Soldering',
                'type' => 'number',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 7,
            ],
            [
                'title' => 'Saldatura Componenti SMD',
                'description' => 'Saldare i componenti SMD seguendo l\'ordine: IC, componenti passivi, altri',
                'category' => 'Soldering',
                'type' => 'photo',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 8,
            ],
            [
                'title' => 'Saldatura Componenti Through-Hole',
                'description' => 'Saldare i componenti through-hole verificando saldature pulite e uniformi',
                'category' => 'Soldering',
                'type' => 'photo',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 9,
            ],
            [
                'title' => 'Ispezione Saldature',
                'description' => 'Ispezionare tutte le saldature al microscopio per corti, ponti, o saldature fredde',
                'category' => 'Soldering',
                'type' => 'photo',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 10,
            ],

            // Testing
            [
                'title' => 'Test Continuità',
                'description' => 'Verificare continuità su punti critici con multimetro',
                'category' => 'Testing',
                'type' => 'measurement',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 11,
            ],
            [
                'title' => 'Test Corti',
                'description' => 'Verificare assenza di cortocircuiti tra VCC/GND e altri net',
                'category' => 'Testing',
                'type' => 'measurement',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 12,
            ],
            [
                'title' => 'Power-On Test',
                'description' => 'Alimentare la scheda e verificare assorbimento corretto',
                'category' => 'Testing',
                'type' => 'measurement',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 13,
            ],
            [
                'title' => 'Test Funzionale Base',
                'description' => 'Eseguire test funzionali base secondo specifiche di progetto',
                'category' => 'Testing',
                'type' => 'checkbox',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 14,
            ],

            // Quality Control
            [
                'title' => 'Ispezione Visiva Finale',
                'description' => 'Controllo visivo generale per difetti, componenti mancanti o danneggiati',
                'category' => 'Quality Control',
                'type' => 'photo',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 15,
            ],
            [
                'title' => 'Pulizia Finale',
                'description' => 'Pulire eventuali residui di flux con alcool isopropilico',
                'category' => 'Quality Control',
                'type' => 'checkbox',
                'is_critical' => false,
                'is_required' => false,
                'sort_order' => 16,
            ],

            // Documentation & Packaging
            [
                'title' => 'Applicazione Etichetta QR',
                'description' => 'Applicare etichetta con QR code identificativo sulla scheda',
                'category' => 'Documentation & Packaging',
                'type' => 'photo',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 17,
            ],
            [
                'title' => 'Documentazione',
                'description' => 'Completare documentazione di assemblaggio e test',
                'category' => 'Documentation & Packaging',
                'type' => 'text',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 18,
            ],
            [
                'title' => 'Imballaggio',
                'description' => 'Imballare la scheda in busta antistatica con protezione adeguata',
                'category' => 'Documentation & Packaging',
                'type' => 'checkbox',
                'is_critical' => false,
                'is_required' => false,
                'sort_order' => 19,
            ],
        ];

        foreach ($items as $item) {
            $template->items()->create($item);
        }
    }

    /**
     * Create production assembly template.
     */
    private function createProductionTemplate(int $userId): void
    {
        $template = AssemblyChecklistTemplate::create([
            'name' => 'Produzione PCB Standard',
            'description' => 'Template per assemblaggio schede in produzione serie',
            'board_type' => 'production',
            'complexity_level' => 'medium',
            'is_default' => true,
            'is_active' => true,
            'created_by' => $userId,
            'metadata' => [
                'created_by' => 'system',
                'version' => '1.0',
                'standards' => ['IPC-A-610 Class 2'],
            ],
        ]);

        $items = [
            // Pre-Assembly
            [
                'title' => 'Setup Postazione ESD',
                'description' => 'Verificare postazione ESD-safe con tappetino e bracciale collegati',
                'category' => 'Pre-Assembly',
                'type' => 'checkbox',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 1,
            ],
            [
                'title' => 'Controllo Batch PCB',
                'description' => 'Verificare numero batch PCB e corrispondenza con ordine di produzione',
                'category' => 'Pre-Assembly',
                'type' => 'checkbox',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 2,
            ],
            [
                'title' => 'Ispezione PCB',
                'description' => 'Controllo PCB secondo IPC-A-610: difetti, contaminazioni, danni',
                'category' => 'Pre-Assembly',
                'type' => 'photo',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 3,
            ],

            // Component Preparation
            [
                'title' => 'Controllo BOM',
                'description' => 'Verificare tutti i componenti vs BOM, quantità e part numbers',
                'category' => 'Component Preparation',
                'type' => 'checkbox',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 4,
            ],
            [
                'title' => 'Controllo Scadenza Componenti',
                'description' => 'Verificare MSL (Moisture Sensitivity Level) e date di scadenza componenti SMD',
                'category' => 'Component Preparation',
                'type' => 'checkbox',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 5,
            ],
            [
                'title' => 'Preparazione Componenti',
                'description' => 'Organizzare componenti per ordine di montaggio',
                'category' => 'Component Preparation',
                'type' => 'checkbox',
                'is_critical' => false,
                'is_required' => false,
                'sort_order' => 6,
            ],

            // SMT Assembly
            [
                'title' => 'Applicazione Pasta Saldante',
                'description' => 'Applicare pasta saldante con stencil, verificare uniformità',
                'category' => 'SMT Assembly',
                'type' => 'photo',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 7,
            ],
            [
                'title' => 'Ispezione Pasta Saldante',
                'description' => 'Controllo deposito pasta: spessore, allineamento, assenza ponti',
                'category' => 'SMT Assembly',
                'type' => 'photo',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 8,
            ],
            [
                'title' => 'Posizionamento Componenti SMD',
                'description' => 'Posizionare componenti SMD secondo assembly drawing',
                'category' => 'SMT Assembly',
                'type' => 'checkbox',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 9,
            ],
            [
                'title' => 'Pre-Reflow Inspection',
                'description' => 'Controllo posizionamento e orientamento componenti pre-rifusione',
                'category' => 'SMT Assembly',
                'type' => 'photo',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 10,
            ],
            [
                'title' => 'Reflow Soldering',
                'description' => 'Rifusione secondo profilo termico approvato',
                'category' => 'SMT Assembly',
                'type' => 'checkbox',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 11,
            ],
            [
                'title' => 'Post-Reflow Inspection',
                'description' => 'AOI o ispezione visiva: ponti, tombstoning, allineamento',
                'category' => 'SMT Assembly',
                'type' => 'photo',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 12,
            ],

            // Through-Hole Assembly
            [
                'title' => 'Inserimento Componenti THT',
                'description' => 'Inserire componenti through-hole secondo sequenza',
                'category' => 'Through-Hole Assembly',
                'type' => 'checkbox',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 13,
            ],
            [
                'title' => 'Saldatura Wave/Manuale',
                'description' => 'Saldatura componenti THT (wave soldering o manuale)',
                'category' => 'Through-Hole Assembly',
                'type' => 'checkbox',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 14,
            ],
            [
                'title' => 'Ispezione Saldature THT',
                'description' => 'Controllo qualità saldature secondo IPC-A-610 Class 2',
                'category' => 'Through-Hole Assembly',
                'type' => 'photo',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 15,
            ],

            // Testing & QC
            [
                'title' => 'In-Circuit Test (ICT)',
                'description' => 'Eseguire ICT se disponibile: corti, aperture, valori componenti',
                'category' => 'Testing & QC',
                'type' => 'checkbox',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 16,
            ],
            [
                'title' => 'Functional Test',
                'description' => 'Test funzionale completo secondo procedura di test',
                'category' => 'Testing & QC',
                'type' => 'checkbox',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 17,
            ],
            [
                'title' => 'Burn-In Test',
                'description' => 'Test di invecchiamento accelerato se richiesto (opzionale)',
                'category' => 'Testing & QC',
                'type' => 'checkbox',
                'is_critical' => false,
                'is_required' => false,
                'sort_order' => 18,
            ],
            [
                'title' => 'Final Visual Inspection',
                'description' => 'Ispezione visiva finale: pulizia, danni, etichettatura',
                'category' => 'Testing & QC',
                'type' => 'photo',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 19,
            ],

            // Documentation & Traceability
            [
                'title' => 'Applicazione Serial Number/QR',
                'description' => 'Applicare serial number o QR code tracciabilità',
                'category' => 'Documentation & Traceability',
                'type' => 'photo',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 20,
            ],
            [
                'title' => 'Documentazione Traveler',
                'description' => 'Completare traveler con tutti i dati di produzione e test',
                'category' => 'Documentation & Traceability',
                'type' => 'text',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 21,
            ],
            [
                'title' => 'Conformal Coating',
                'description' => 'Applicare coating protettivo se richiesto',
                'category' => 'Final Operations',
                'type' => 'photo',
                'is_critical' => false,
                'is_required' => false,
                'sort_order' => 22,
            ],
            [
                'title' => 'Packaging Finale',
                'description' => 'Imballare in busta antistatica con etichetta identificativa',
                'category' => 'Final Operations',
                'type' => 'checkbox',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 23,
            ],
        ];

        foreach ($items as $item) {
            $template->items()->create($item);
        }
    }

    /**
     * Create generic fallback template.
     */
    private function createGenericTemplate(int $userId): void
    {
        $template = AssemblyChecklistTemplate::create([
            'name' => 'Generic PCB Assembly',
            'description' => 'Template generico per assemblaggio PCB',
            'board_type' => 'generic',
            'complexity_level' => 'simple',
            'is_default' => true,
            'is_active' => true,
            'created_by' => $userId,
            'metadata' => [
                'created_by' => 'system',
                'version' => '1.0',
            ],
        ]);

        $items = [
            [
                'title' => 'ESD Protection',
                'description' => 'Verify ESD-safe workstation setup',
                'category' => 'Pre-Assembly',
                'type' => 'checkbox',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 1,
            ],
            [
                'title' => 'PCB Inspection',
                'description' => 'Inspect PCB for visible damage',
                'category' => 'Pre-Assembly',
                'type' => 'photo',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 2,
            ],
            [
                'title' => 'Component Verification',
                'description' => 'Verify all components match BOM',
                'category' => 'Component Inspection',
                'type' => 'checkbox',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 3,
            ],
            [
                'title' => 'Component Assembly',
                'description' => 'Assemble components according to documentation',
                'category' => 'Assembly',
                'type' => 'photo',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 4,
            ],
            [
                'title' => 'Solder Joint Inspection',
                'description' => 'Inspect all solder joints for quality',
                'category' => 'Quality Control',
                'type' => 'photo',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 5,
            ],
            [
                'title' => 'Continuity Test',
                'description' => 'Test critical connections for continuity',
                'category' => 'Testing',
                'type' => 'measurement',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 6,
            ],
            [
                'title' => 'Short Circuit Test',
                'description' => 'Verify no short circuits between power rails',
                'category' => 'Testing',
                'type' => 'measurement',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 7,
            ],
            [
                'title' => 'Functional Test',
                'description' => 'Perform basic functional testing',
                'category' => 'Testing',
                'type' => 'checkbox',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 8,
            ],
            [
                'title' => 'Final Inspection',
                'description' => 'Final visual inspection and cleaning',
                'category' => 'Quality Control',
                'type' => 'photo',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 9,
            ],
            [
                'title' => 'QR Code Application',
                'description' => 'Apply QR code label for traceability',
                'category' => 'Documentation',
                'type' => 'photo',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 10,
            ],
            [
                'title' => 'Documentation',
                'description' => 'Complete assembly documentation',
                'category' => 'Documentation',
                'type' => 'text',
                'is_critical' => true,
                'is_required' => true,
                'sort_order' => 11,
            ],
            [
                'title' => 'Packaging',
                'description' => 'Package board in anti-static bag',
                'category' => 'Packaging',
                'type' => 'checkbox',
                'is_critical' => false,
                'is_required' => false,
                'sort_order' => 12,
            ],
        ];

        foreach ($items as $item) {
            $template->items()->create($item);
        }
    }
}
