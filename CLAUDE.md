# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

# Supernova Management Software - Laravel + Filament v3

## Essential Commands

### Development Environment
```bash
# Start Docker containers
docker-compose up -d

# Stop Docker containers
docker-compose down

# Access PHP container
docker-compose exec app bash

# View logs
docker-compose logs -f app
docker-compose logs -f nginx
```

### Laravel Development
```bash
# Run migrations
php artisan migrate
php artisan migrate:fresh --seed  # Reset and seed database

# Generate Filament resources
php artisan make:filament-resource ResourceName

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear

# Queue management
php artisan queue:work
php artisan horizon  # If Horizon is configured

# Run tests
php artisan test
php artisan test --filter TestName
php artisan test --parallel

# Seed database
php artisan db:seed
php artisan db:seed --class=ComponentSeeder

# Create models with migration
php artisan make:model ModelName -m

# Compile assets
npm run dev
npm run build
npm run watch
```

### Database Management
```bash
# Create migration
php artisan make:migration create_table_name

# Rollback migrations
php artisan migrate:rollback
php artisan migrate:rollback --step=3

# Check migration status
php artisan migrate:status
```

### Filament-Specific Commands
```bash
# Create Filament widget
php artisan make:filament-widget WidgetName

# Create custom Filament page
php artisan make:filament-page PageName

# Create Filament relation manager
php artisan make:filament-relation-manager ResourceName relationship

# Clear Filament cache
php artisan filament:cache-components
```

## Architecture Overview

### Core Application Structure
The application follows a modular architecture with clear separation of concerns:

1. **Filament Resources** (`app/Filament/Resources/`): Admin panel CRUD interfaces with advanced features
2. **Services Layer** (`app/Services/`): Business logic encapsulation for complex operations
3. **Models** (`app/Models/`): Eloquent models with comprehensive relationships
4. **Custom Pages** (`app/Filament/Pages/`): Specialized interfaces (iBOM viewer, ArUco scanner, BOM analysis)
5. **API Integration** (`app/Services/Api/`): External supplier APIs (Mouser, DigiKey)

### Key Architectural Patterns

#### 1. Environment-Agnostic File System
All file paths are configurable via environment variables to support multiple deployment scenarios:
```php
// Access files using Laravel Storage disks
Storage::disk('syncthing_clients')->get($path);
Storage::disk('syncthing_documents')->put($path, $content);
```

#### 2. Service Pattern for Complex Operations
Complex business logic is encapsulated in service classes:
- `ArUcoService`: Marker generation and scanning
- `InteractiveBomService`: iBOM generation and management
- `ComponentImportService`: Multi-supplier import orchestration
- `BomService`: BOM calculations and analysis

#### 3. Filament Resource Extensions
Resources extend base Filament functionality with:
- Custom actions for bulk operations
- Advanced filters and search
- Relation managers for complex associations
- Custom widgets for data visualization

#### 4. Queue Jobs for Async Operations
Long-running tasks use Laravel queues:
- Component import from suppliers
- PDF generation for quotations
- Bulk email notifications
- Data synchronization

#### 5. File Upload Pattern - NEXTCLOUD FIRST

**REGOLA FONDAMENTALE:** Quando l'utente dice "salvare su Nextcloud" o si lavora con documenti permanenti, **SEMPRE** usare `NextcloudService`.

**Pattern Obbligatorio:**

1. **FileUpload temporaneo:**
   ```php
   Forms\Components\FileUpload::make('document_temp')
       ->disk('public')  // Mai 'local' o 'syncthing*'
       ->directory('temp_documents')
       ->helperText('Verrà caricato automaticamente su Nextcloud')
   ```

2. **Upload su Nextcloud nell'action:**
   ```php
   use App\Services\NextcloudService;

   ->action(function (array $data) {
       if (!empty($data['document_temp'])) {
           $localPath = storage_path('app/public/' . $data['document_temp']);
           $nextcloudPath = "path/appropriato/{$filename}";

           $nextcloudService = app(NextcloudService::class);
           $uploaded = $nextcloudService->uploadDocument($localPath, $nextcloudPath);

           if ($uploaded) {
               $record->nextcloud_path = $nextcloudPath;
               $record->save();

               // Cancella temp
               if (file_exists($localPath)) {
                   unlink($localPath);
               }
           }
       }
   })
   ```

3. **Salva SOLO il path Nextcloud nel database:**
   ```php
   // Migration
   $table->string('nextcloud_path')->nullable();

   // Model fillable
   'nextcloud_path',
   ```

**MAI fare:**
- ❌ Salvare file permanenti su `disk('local')`
- ❌ Usare `disk('syncthing*')` (obsoleto)
- ❌ Salvare path locale nel database
- ❌ Usare Storage::disk() per documenti (solo per temp)

**Eccezioni (usare disk public):**
- ✅ File temporanei (elaborazione, cache)
- ✅ Immagini pubbliche (loghi, product images)
- ✅ Asset di sistema

### Database Architecture

The database uses PostgreSQL with a comprehensive schema:

**Core Tables**:
- `components`: Electronic components with 55+ technical fields
- `customers`: CRM with Italian business compliance
- `projects`: Project management with milestones
- `quotations`: Multi-item quotation system
- `inventory_movements`: Stock tracking
- `boms`: Bill of Materials management
- `pcb_files`: PCB version control

**Key Relationships**:
- Components → Categories (many-to-one)
- Projects → Customers (many-to-one)
- Projects → Components (many-to-many via BOMs)
- Components → Suppliers (many-to-many)
- Projects → Tasks → Time Entries (nested relationships)

### Nextcloud Integration

**IMPORTANTE:** Il sistema usa **Nextcloud** (NON Syncthing) per tutti i file permanenti.

#### Quando Salvare su Nextcloud

**SEMPRE usare Nextcloud per:**
- ✅ Fatture (ricevute/emesse)
- ✅ Preventivi/Quotazioni
- ✅ Contratti
- ✅ Documenti progetto (PDF, CAD, Gerber, BOM, Firmware)
- ✅ Certificati e conformità
- ✅ Datasheets e manuali
- ✅ Qualsiasi documento permanente cliente/fornitore

**NON usare Nextcloud per:**
- ❌ File temporanei (elaborazione, cache)
- ❌ Loghi e immagini pubbliche (usare `public` disk)
- ❌ Asset di sistema (template, branding)

#### Pattern Upload Standard

```php
use App\Services\NextcloudService;

// 1. FileUpload temporaneo
Forms\Components\FileUpload::make('invoice_pdf_temp')
    ->disk('public')
    ->directory('temp_invoices')
    ->helperText('Verrà automaticamente caricato su Nextcloud')
    ->afterStateUpdated(function (Set $set, $state) {
        if ($state) {
            $localPath = storage_path('app/public/' . $state);
            $nextcloudPath = "path/appropriato/{$filename}";

            $nextcloudService = app(NextcloudService::class);
            $uploaded = $nextcloudService->uploadDocument($localPath, $nextcloudPath);

            if ($uploaded) {
                $set('nextcloud_path', $nextcloudPath);
                // Cancella temp
                if (file_exists($localPath)) {
                    unlink($localPath);
                }
            }
        }
    }),

// 2. Salva path Nextcloud nel database
'nextcloud_path' => 'nullable|string'
```

#### Struttura Nextcloud

```
{NEXTCLOUD_ROOT}/
│
├─── Clienti/
│    │
│    ├─── {CUSTOMER_CODE} - {COMPANY_NAME}/                    # Es: C000001 - ACME SRL
│    │    │
│    │    ├─── _customer_info.json                             # 📄 INFO FATTURAZIONE CLIENTE
│    │    │
│    │    ├─── 01_Anagrafica/
│    │    │    ├─── Visura_Camerale/
│    │    │    ├─── Documenti_Identita/
│    │    │    ├─── Certificazioni/
│    │    │    └─── Contratti/                                 # 📄 CONTRATTI GENERATI
│    │    │         └─── {CONTRACT_NUMBER}_{DATE}.pdf
│    │    │
│    │    ├─── 02_Comunicazioni/
│    │    │    ├─── Email/
│    │    │    ├─── Lettere/
│    │    │    └─── Verbali_Riunioni/
│    │    │
│    │    ├─── 03_Progetti/
│    │    │    │
│    │    │    └─── {PROJECT_CODE}/
│    │    │         │
│    │    │         ├─── _project_info.json                    # 📄 INFO PROGETTO
│    │    │         ├─── _components_used.json                 # 📄 TRACCIABILITÀ COMPONENTI
│    │    │         │
│    │    │         ├─── 01_Preventivi/
│    │    │         │    ├─── Bozze/
│    │    │         │    ├─── Inviati/
│    │    │         │    └─── Accettati/
│    │    │         │         └─── {QUOTATION_NUMBER}.pdf
│    │    │         │
│    │    │         ├─── 02_Progettazione/
│    │    │         │    ├─── KiCad/
│    │    │         │    │    ├─── {VERSION}/
│    │    │         │    │    │    ├─── project.kicad_pro
│    │    │         │    │    │    ├─── schematic.kicad_sch
│    │    │         │    │    │    ├─── pcb.kicad_pcb
│    │    │         │    │    │    ├─── libraries/             # 📁 LIB KICAD
│    │    │         │    │    │    │    ├─── symbols/
│    │    │         │    │    │    │    ├─── footprints/
│    │    │         │    │    │    │    └─── 3d_models/
│    │    │         │    │    │    └─── _Archive/
│    │    │         │    │    │
│    │    │         │    │    └─── SystemEngineering/          # 📁 SYSTEM ENGINEERING
│    │    │         │    │         ├─── Architecture/
│    │    │         │    │         ├─── Requirements/
│    │    │         │    │         ├─── Specifications/
│    │    │         │    │         ├─── Testing/
│    │    │         │    │         └─── Validation/
│    │    │         │    │
│    │    │         │    ├─── Gerber/
│    │    │         │    ├─── BOM/
│    │    │         │    ├─── 3D_Models/
│    │    │         │    ├─── Datasheet/
│    │    │         │    ├─── Firmware/
│    │    │         │    └─── Mechanical/
│    │    │         │
│    │    │         ├─── 03_Produzione/
│    │    │         │    ├─── Ordini_PCB/
│    │    │         │    ├─── Ordini_Componenti/
│    │    │         │    │    └─── _components_allocation.json  # 📄 ALLOCAZIONE COMPONENTI
│    │    │         │    ├─── Assembly_Instructions/
│    │    │         │    ├─── Test_Reports/
│    │    │         │    └─── Production_Logs/
│    │    │         │
│    │    │         ├─── 04_Certificazioni_Conformita/
│    │    │         ├─── 05_Documentazione/
│    │    │         ├─── 06_Consegna/
│    │    │         └─── 07_Assistenza/
│    │    │
│    │    └─── 04_Fatturazione/
│    │         │
│    │         ├─── _billing_summary.json                      # 📄 RIEPILOGO FATTURAZIONE
│    │         │
│    │         ├─── Fatture_Emesse/
│    │         │    ├─── {YEAR}/
│    │         │    │    ├─── {INVOICE_NUMBER}_{DATE}.pdf
│    │         │    │    └─── _invoices_manifest.json          # 📄 MANIFEST FATTURE
│    │         │    └─── _Non_Pagate/
│    │         │
│    │         ├─── Fatture_Ricevute/                          # 📁 FATTURE RICEVUTE (fornitori per questo cliente)
│    │         │    └─── {YEAR}/
│    │         │         └─── {SUPPLIER}_{DATE}_{INVOICE_NUMBER}.pdf
│    │         │
│    │         ├─── F24/                                        # 📁 F24 AGGIUNTI
│    │         │    └─── {YEAR}/
│    │         │         └─── F24_{MONTH}_{YEAR}.pdf
│    │         │
│    │         ├─── Note_Credito/
│    │         │    └─── {YEAR}/
│    │         │
│    │         └─── Pagamenti/
│    │              ├─── Ricevute/
│    │              └─── Bonifici/
│    │
│    └─── _Templates/
│
├─── Magazzino/
│    │
│    ├─── _inventory_summary.json                              # 📄 RIEPILOGO MAGAZZINO
│    │
│    ├─── Componenti/
│    │    └─── {CATEGORY}/
│    │         └─── {COMPONENT_CODE}/
│    │              ├─── _component_info.json                  # 📄 INFO COMPONENTE
│    │              ├─── Datasheet.pdf
│    │              ├─── Photos/
│    │              └─── Stock_History/                        # 📁 STORICO MOVIMENTI
│    │                   └─── movements_{YEAR}.json
│    │
│    ├─── Fatture_Magazzino/                                   # 📁 FATTURE MAGAZZINO
│    │    │
│    │    ├─── Fornitori/                                      # Fatture da fornitori per componenti
│    │    │    └─── {YEAR}/
│    │    │         └─── {SUPPLIER}_{DATE}_{INVOICE_NUMBER}.pdf
│    │    │              + {SUPPLIER}_{DATE}_{INVOICE_NUMBER}_components.json  # Link componenti
│    │    │
│    │    ├─── Dogana/                                         # 📁 FATTURE DOGANA
│    │    │    └─── {YEAR}/
│    │    │         └─── CUSTOMS_{DATE}_{INVOICE_NUMBER}.pdf
│    │    │
│    │    ├─── Macchinari/                                     # 📁 FATTURE MACCHINARI
│    │    │    └─── {YEAR}/
│    │    │         └─── EQUIPMENT_{DATE}_{INVOICE_NUMBER}.pdf
│    │    │
│    │    └─── Generali/                                       # 📁 FATTURE GENERALI/RESTOCK
│    │         └─── {YEAR}/
│    │              └─── GENERAL_{DATE}_{INVOICE_NUMBER}.pdf
│    │
│    ├─── Ordini/
│    │    └─── {ORDER_NUMBER}/
│    │         ├─── order_details.json
│    │         ├─── packing_list.pdf
│    │         └─── _linked_invoice.json                       # 📄 LINK A FATTURA
│    │
│    └─── Reports/
│         ├─── Stock_Levels/
│         ├─── Component_Usage/                                # 📁 USO COMPONENTI PER CLIENTE
│         │    └─── by_customer_{YEAR}.json
│         └─── Financial/
│              ├─── monthly_costs_{YEAR}.json
│              └─── warehouse_value_{YEAR}.json
│
├─── Documenti SRL/
│    ├─── Amministrazione/
│    │    └─── F24/                                            # 📁 F24 AZIENDALI
│    │         └─── {YEAR}/
│    ├─── Legale/
│    ├─── Fiscale/
│    │    ├─── Bilanci/                                        # 📁 BILANCI
│    │    │    └─── {YEAR}/
│    │    │         ├─── bilancio_{YEAR}.pdf
│    │    │         └─── _billing_analysis.json                # 📄 ANALISI AUTOMATICA
│    │    └─── Dichiarazioni/
│    └─── HR/
│
└─── Analytics/                                                 # 📁 NUOVA CARTELLA ANALYTICS
     ├─── Billing/
     │    ├─── monthly_revenue_{YEAR}.json
     │    ├─── monthly_costs_{YEAR}.json
     │    ├─── profit_loss_{YEAR}.json
     │    └─── forecasts_{YEAR}.json                           # 📄 PREVISIONI FATTURATO
     │
     ├─── Projects/
     │    ├─── active_projects.json
     │    ├─── components_allocation.json
     │    └─── production_status.json
     │
     └─── Customers/
          ├─── customer_value_{YEAR}.json
          └─── payment_terms_tracking.json                     # 📄 TRACKING PAGAMENTI 30%+70%
```

#### NextcloudService Metodi

```php
$nextcloudService = app(NextcloudService::class);

// Upload generico
$nextcloudService->uploadDocument($localPath, $nextcloudPath);

// Upload specifici (usano path standard)
$nextcloudService->uploadQuotation($quotation, $pdfPath);
$nextcloudService->uploadProjectDocument($projectDocument, $filePath);
$nextcloudService->uploadInvoiceIssued($invoice, $pdfPath);

// Utility
$nextcloudService->getCustomerBasePath($customer);
$nextcloudService->getProjectBasePath($project);
$nextcloudService->ensureFolderExists($path);
```

### API Integrations

#### Mouser API
- OAuth2 authentication
- Component search and import
- Real-time pricing and availability

#### DigiKey API
- OAuth2 authentication setup
- Component catalog access
- Bulk import capabilities

### Custom Filament Pages

1. **InteractiveBomViewer** (`app/Filament/Pages/InteractiveBomViewer.php`)
   - Real-time BOM visualization
   - Component placement tracking
   - Cost analysis

2. **ArUcoScanner** (`app/Filament/Pages/ArUcoScanner.php`)
   - Live camera scanning
   - Marker generation
   - Inventory tracking

3. **BomCostAnalysis** (`app/Filament/Pages/BomCostAnalysis.php`)
   - Multi-BOM comparison
   - Cost breakdown
   - Supplier optimization

## Development Workflow

### Adding New Features

1. **Create Migration**: Define database schema
2. **Create Model**: Define relationships and attributes
3. **Generate Filament Resource**: `php artisan make:filament-resource`
4. **Implement Service**: Encapsulate business logic
5. **Add Tests**: Feature and unit tests
6. **Update Seeder**: Add test data

### Modifying Existing Features

1. **Check existing implementation**: Review model, resource, and service
2. **Create migration if needed**: For schema changes
3. **Update Filament resource**: Forms, tables, actions
4. **Update service logic**: Maintain separation of concerns
5. **Test thoroughly**: Run existing tests

### Working with Suppliers API

1. **Check credentials in `.env`**
2. **Use existing service classes**
3. **Handle rate limiting and errors**
4. **Cache responses when appropriate**

## Testing Strategy

### Run All Tests
```bash
php artisan test
```

### Run Specific Test Suite
```bash
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit
```

### Run Single Test
```bash
php artisan test --filter=ComponentTest
php artisan test tests/Feature/ComponentManagementTest.php
```

### Create New Test
```bash
php artisan make:test ComponentApiTest
php artisan make:test ComponentServiceTest --unit
```

## Important Considerations

### Security
- All file uploads are validated
- User permissions use Filament policies
- API endpoints require authentication
- Sensitive data in `.env` only

### Performance
- Use eager loading for relationships
- Implement caching for expensive queries
- Queue long-running operations
- Optimize database indexes

### Filament Customizations
- Custom themes in `resources/css/filament/`
- Custom fields in `app/Forms/Components/`
- Custom table columns in `app/Tables/Columns/`
- Widgets in `app/Filament/Widgets/`

### Environment Variables
Critical environment variables that must be configured:
```env
# Database
DB_CONNECTION=pgsql
DB_HOST=
DB_PORT=5432
DB_DATABASE=supernova
DB_USERNAME=
DB_PASSWORD=

# Nextcloud Configuration
NEXTCLOUD_URL=https://your-nextcloud-url
NEXTCLOUD_USERNAME=admin
NEXTCLOUD_PASSWORD=your-password
NEXTCLOUD_BASE_PATH=/remote.php/dav/files/admin

# API Keys (if using)
MOUSER_API_KEY=
DIGIKEY_CLIENT_ID=
DIGIKEY_CLIENT_SECRET=

# AI Integration (Ollama local)
OLLAMA_URL=http://localhost:11434
OLLAMA_MODEL=llama3.1:8b

# Queue Driver
QUEUE_CONNECTION=redis
REDIS_HOST=redis
```

## Debugging Tips

### View SQL Queries
```php
\DB::enableQueryLog();
// Your code here
dd(\DB::getQueryLog());
```

### Clear All Caches
```bash
php artisan optimize:clear
```

### Rebuild Autoloader
```bash
composer dump-autoload
```

### Check Failed Jobs
```bash
php artisan queue:failed
php artisan queue:retry all
```

## Deployment Checklist

1. Set `APP_ENV=production` and `APP_DEBUG=false`
2. Run `php artisan config:cache`
3. Run `php artisan route:cache`
4. Run `php artisan view:cache`
5. Run `php artisan optimize`
6. Run `npm run build`
7. Set up SSL certificates
8. Configure backup strategy
9. Set up monitoring
10. Test all critical paths