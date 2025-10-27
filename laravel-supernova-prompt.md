```xml
<progetto>
  <nome>Supernova Management Software - Laravel Edition</nome>
  <descrizione>
    Sistema completo per automatizzare e velocizzare ogni aspetto della gestione di un'azienda di elettronica, 
    sviluppato con Laravel 11, Filament v3, Tailwind CSS e Docker per massima efficienza e rapidità di sviluppo.
  </descrizione>
  
  <istruzioni_preliminari>
    Prima di procedere con qualsiasi operazione, consulta sempre il file CLAUDE.md che contiene tutte le linee guida, 
    le convenzioni di codice, le best practices e le istruzioni dettagliate per il progetto Supernova Management Software 
    con stack Laravel + Filament v3.
  </istruzioni_preliminari>
  
  <posizione_progetto>
    Il progetto deve essere completamente modulare e environment-agnostic:
    
    - **Sviluppo attuale**: Crea in `G:\Supernova\supernova-management\`
    - **Produzione futura**: Deploy su VPS OVH con percorsi Linux standard
    - **Docker**: Percorsi containerizzati indipendenti
    
    IMPORTANTE: Tutti i percorsi devono essere configurabili tramite variabili d'ambiente (.env) 
    per garantire portabilità completa tra ambienti diversi.
  </posizione_progetto>

  <configurazione_environment>
    Crea sistema modulare per gestione percorsi:
    
    <env_variables>
      # File System Paths (Environment-specific)
      SYNCTHING_ROOT_PATH=G:\Supernova                    # Dev: G:\Supernova | Prod: /opt/supernova-data
      SYNCTHING_CLIENTS_PATH=${SYNCTHING_ROOT_PATH}/Clienti
      SYNCTHING_DOCUMENTS_PATH=${SYNCTHING_ROOT_PATH}/Documenti SRL
      SYNCTHING_WAREHOUSE_PATH=${SYNCTHING_ROOT_PATH}/Magazzino
      SYNCTHING_TEMPLATES_PATH=${SYNCTHING_ROOT_PATH}/Modelli Documenti
      
      # Laravel Storage Disks
      FILESYSTEM_DISK_CLIENTS=syncthing_clients
      FILESYSTEM_DISK_DOCUMENTS=syncthing_documents
      FILESYSTEM_DISK_WAREHOUSE=syncthing_warehouse
    </env_variables>
    
    <laravel_storage_config>
      Configura in config/filesystems.php storage disks dinamici che puntano 
      alle cartelle Syncthing tramite environment variables, permettendo 
      cambio seamless tra Windows (sviluppo) e Linux (produzione).
    </laravel_storage_config>
  </configurazione_environment>

  <setup_iniziale_completo>
    Esegui questi comandi in sequenza per configurare l'intero ambiente:

    <step_1_prerequisiti>
      # Verifica prerequisiti (solo check, non installare)
      php --version    # Deve essere >= 8.2
      composer --version
      node --version
      npm --version
      docker --version
      docker-compose --version
    </step_1_prerequisiti>

    <step_2_creazione_progetto>
      # Naviga nella cartella principale
      cd G:\Supernova
      
      # Crea nuovo progetto Laravel
      composer create-project laravel/laravel supernova-management
      
      # Entra nella cartella del progetto
      cd supernova-management
      
      # Installa Laravel Breeze per autenticazione (opzionale, Filament ha la sua)
      # composer require laravel/breeze --dev
      # php artisan breeze:install blade
    </step_2_creazione_progetto>

    <step_3_configurazione_database>
      # Copia .env.example a .env
      copy .env.example .env
      
      # Configura database PostgreSQL e percorsi Syncthing nel file .env:
      
      # Database Configuration
      DB_CONNECTION=pgsql
      DB_HOST=127.0.0.1
      DB_PORT=5432
      DB_DATABASE=supernova
      DB_USERNAME=supernova
      DB_PASSWORD=password
      
      # File System Paths (Development)
      SYNCTHING_ROOT_PATH=G:\Supernova
      SYNCTHING_CLIENTS_PATH=${SYNCTHING_ROOT_PATH}/Clienti
      SYNCTHING_DOCUMENTS_PATH=${SYNCTHING_ROOT_PATH}/Documenti SRL
      SYNCTHING_WAREHOUSE_PATH=${SYNCTHING_ROOT_PATH}/Magazzino
      SYNCTHING_TEMPLATES_PATH=${SYNCTHING_ROOT_PATH}/Modelli Documenti
      SYNCTHING_PROTOTYPES_PATH=${SYNCTHING_ROOT_PATH}/Prototipi
      
      # Laravel Storage Disk Configuration
      FILESYSTEM_DISK_CLIENTS=syncthing_clients
      FILESYSTEM_DISK_DOCUMENTS=syncthing_documents
      FILESYSTEM_DISK_WAREHOUSE=syncthing_warehouse
      FILESYSTEM_DISK_TEMPLATES=syncthing_templates
      FILESYSTEM_DISK_PROTOTYPES=syncthing_prototypes
      
      # Genera application key
      php artisan key:generate
    </step_3_configurazione_database>

    <step_4_installazione_filament>
      # Installa Filament v3
      composer require filament/filament:"^3.0"
      
      # Pubblica assets Filament
      php artisan filament:install --panels
      
      # Crea user admin
      php artisan make:filament-user
      
      # Installa plugin aggiuntivi Filament
      composer require filament/spatie-laravel-media-library-plugin:"^3.0"
      composer require filament/spatie-laravel-settings-plugin:"^3.0"
      composer require filament/spatie-laravel-tags-plugin:"^3.0"
    </step_4_installazione_filament>

    <step_5_docker_setup>
      # Crea cartella docker
      mkdir docker
      mkdir docker/nginx
      mkdir docker/php
      
      # Crea Dockerfile per PHP
      # Crea docker-compose.yml
      # Crea nginx.conf
      # Crea php.ini personalizzato
    </step_5_docker_setup>

    <step_6_asset_compilation>
      # Installa dipendenze Node.js
      npm install
      
      # Installa Tailwind CSS
      npm install -D tailwindcss @tailwindcss/forms @tailwindcss/typography
      
      # Inizializza Tailwind
      npx tailwindcss init -p
      
      # Compila assets
      npm run build
    </step_6_asset_compilation>

    <step_7_packages_aggiuntivi>
      # Pacchetti per funzionalità avanzate
      composer require spatie/laravel-permission
      composer require spatie/laravel-activitylog
      composer require spatie/laravel-media-library
      composer require maatwebsite/excel
      composer require barryvdh/laravel-dompdf
      composer require league/flysystem-aws-s3-v3
      composer require laravel/horizon
      composer require laravel/scout
      composer require meilisearch/meilisearch-php
    </step_7_packages_aggiuntivi>
  </setup_iniziale_completo>

  <architettura_sistema>
    Il sistema Laravel + Filament utilizzerà:
    
    1. **Laravel 11** come framework backend principale
    2. **Filament v3** per admin panel con CRUD automatici
    3. **PostgreSQL** come database principale
    4. **Redis** per cache e queue
    5. **MeiliSearch** per ricerca full-text
    6. **Docker Compose** per containerizzazione
    7. **Tailwind CSS** per styling
    8. **Livewire 3** per interattività (incluso in Filament)
  </architettura_sistema>

  <moduli_da_sviluppare>
    Sviluppa i moduli in questo ordine preciso:

    <modulo_1_inventario>
      <nome>Gestione Inventario Componenti Elettronici</nome>
      <priorita>1</priorita>
      
      <database_migrations>
        - categories: id, name, description, parent_id, created_at, updated_at
        - suppliers: id, name, website, email, phone, api_key, api_secret, created_at, updated_at
        - components: id, part_number, name, description, category_id, supplier_id, datasheet_url, 
                     specifications(json), price, currency, stock_quantity, min_stock_level, 
                     location, aruco_marker_id, created_at, updated_at
        - inventory_movements: id, component_id, type(in/out), quantity, reason, user_id, 
                              previous_stock, new_stock, created_at
      </database_migrations>
      
      <models_eloquent>
        - Category: con relationship hasMany components, belongsTo parent, hasMany children
        - Supplier: con relationship hasMany components
        - Component: con relationships belongsTo category, belongsTo supplier, hasMany movements
        - InventoryMovement: con relationships belongsTo component, belongsTo user
      </models_eloquent>
      
      <filament_resources>
        - CategoryResource: con tree view per categorie hierarchiche
        - SupplierResource: con form completo e integrazione API
        - ComponentResource: con form avanzato, gestione stock, upload datasheet
        - InventoryMovementResource: read-only con filtri avanzati
      </filament_resources>
      
      <widgets_dashboard>
        - StockLevelWidget: componenti in esaurimento
        - RecentMovementsWidget: ultimi movimenti
        - CategoryDistributionWidget: grafico distribuzione per categoria
        - SupplierStatsWidget: statistiche fornitori
      </widgets_dashboard>
    </modulo_1_inventario>

    <modulo_2_crm>
      <nome>Sistema CRM per Gestione Clienti e Progetti</nome>
      <priorita>2</priorita>
      
      <database_migrations>
        - customers: id, name, company, email, phone, address, vat_number, contact_person, 
                    notes, status, created_at, updated_at
        - projects: id, name, description, customer_id, status, start_date, end_date, 
                   budget, progress_percentage, priority, created_at, updated_at
        - project_components: id, project_id, component_id, quantity_needed, quantity_used,
                             cost_per_unit, notes, created_at, updated_at
        - project_tasks: id, project_id, name, description, assigned_to, status, due_date,
                        completed_at, created_at, updated_at
      </database_migrations>
      
      <filament_resources>
        - CustomerResource: con info complete e progetti associati
        - ProjectResource: con gestione timeline, componenti, task
        - ProjectComponentResource: per gestione BOM progetti
        - ProjectTaskResource: per task management
      </filament_resources>
      
      <integration_inventario>
        - Automatic stock reservation per progetti
        - BOM generation da componenti
        - Cost calculation automatico
      </integration_inventario>
    </modulo_2_crm>

    <modulo_3_preventivi>
      <nome>Sistema Generazione Preventivi e Documenti</nome>
      <priorita>3</priorita>
      
      <database_migrations>
        - quotations: id, customer_id, project_id, number, date, validity_days, 
                     subtotal, tax_rate, tax_amount, total, status, notes, created_at, updated_at
        - quotation_items: id, quotation_id, component_id, description, quantity, 
                          unit_price, discount_percentage, total_price, created_at, updated_at
        - documents: id, type, related_id, related_type, file_path, original_name, 
                    mime_type, size, created_at, updated_at
      </database_migrations>
      
      <pdf_generation>
        - Template preventivi con dompdf
        - Generazione automatica DDT
        - Template personalizzabili per cliente
      </pdf_generation>
      
      <filament_resources>
        - QuotationResource: con builder preventivi drag-and-drop
        - QuotationItemResource: per dettaglio voci
        - DocumentResource: per gestione allegati
      </filament_resources>
    </modulo_3_preventivi>

    <modulo_4_documentazione>
      <nome>Gestione Documentazione Tecnica</nome>
      <priorita>4</priorita>
      
      <database_migrations>
        - technical_documents: id, title, description, category, component_id, project_id,
                              file_path, version, tags(json), created_by, created_at, updated_at
        - document_versions: id, document_id, version, file_path, changes, created_by, created_at
      </database_migrations>
      
      <file_management>
        - Upload multipli con drag-and-drop
        - Preview documenti PDF
        - Versioning automatico
        - Full-text search nei documenti
      </file_management>
    </modulo_4_documentazione>

    <modulo_5_aruco>
      <nome>Sistema Tracciamento ArUco</nome>
      <priorita>5</priorita>
      
      <database_migrations>
        - aruco_markers: id, marker_code, component_id, location_x, location_y, location_z,
                        last_scan_at, scan_count, created_at, updated_at
        - location_scans: id, marker_id, scanned_by, location_x, location_y, location_z,
                         confidence_level, created_at
      </database_migrations>
      
      <api_endpoints>
        - POST /api/aruco/scan: per ricevere scan da app mobile
        - GET /api/aruco/component/{id}: per ottenere info componente
        - POST /api/aruco/update-location: per aggiornare posizione
      </api_endpoints>
    </modulo_5_aruco>

    <modulo_6_api_fornitori>
      <nome>Integrazione API Fornitori</nome>
      <priorita>6</priorita>
      
      <jobs_queue>
        - SyncComponentsFromMouserJob
        - SyncComponentsFromDigiKeyJob
        - UpdatePricesFromSuppliersJob
        - ImportCatalogJob
      </jobs_queue>
      
      <scheduled_tasks>
        - Sync quotidiano prezzi
        - Update settimanale cataloghi
        - Check disponibilità componenti
      </scheduled_tasks>
    </modulo_6_api_fornitori>

    <modulo_7_analytics>
      <nome>Dashboard Analytics e Reportistica</nome>
      <priorita>7</priorita>
      
      <widgets_avanzati>
        - Filament Chart widgets per KPI
        - Custom Livewire components per dashboard
        - Export automatico reports Excel/PDF
        - Scheduled reports via email
      </widgets_avanzati>
    </modulo_7_analytics>

    <modulo_8_gantt>
      <nome>Gestione Progetti con Gantt</nome>
      <priorita>8</priorita>
      
      <custom_pages>
        - Custom Filament page con Gantt chart JavaScript
        - Integrazione con FullCalendar.js
        - Sync con Google Calendar API
      </custom_pages>
    </modulo_8_gantt>

    <modulo_9_syncthing>
      <nome>Integrazione File System Syncthing</nome>
      <priorita>9</priorita>
      
      <file_watchers>
        - Laravel file watchers per cartelle Syncthing
        - Automatic file indexing
        - Metadata extraction
      </file_watchers>
    </modulo_9_syncthing>
  </moduli_da_sviluppare>

  <files_configurazione_modulare>
    Crea questi file per supportare il sistema modulare:

    <filesystem_config>
      # config/filesystems.php - Aggiungi questi disks
      'syncthing_clients' => [
          'driver' => 'local',
          'root' => env('SYNCTHING_CLIENTS_PATH', storage_path('app/clients')),
          'url' => env('APP_URL').'/storage/clients',
          'visibility' => 'private',
      ],
      'syncthing_documents' => [
          'driver' => 'local',
          'root' => env('SYNCTHING_DOCUMENTS_PATH', storage_path('app/documents')),
          'url' => env('APP_URL').'/storage/documents',
          'visibility' => 'private',
      ],
      'syncthing_warehouse' => [
          'driver' => 'local',
          'root' => env('SYNCTHING_WAREHOUSE_PATH', storage_path('app/warehouse')),
          'url' => env('APP_URL').'/storage/warehouse',
          'visibility' => 'private',
      ],
    </filesystem_config>

    <syncthing_service_provider>
      # app/Providers/SyncthingServiceProvider.php
      <?php
      namespace App\Providers;
      
      use Illuminate\Support\ServiceProvider;
      use App\Services\SyncthingPathManager;
      
      class SyncthingServiceProvider extends ServiceProvider
      {
          public function register()
          {
              $this->app->singleton('syncthing.paths', function ($app) {
                  return new SyncthingPathManager();
              });
          }
          
          public function boot()
          {
              // Register custom filesystem disks
              $this->ensureSyncthingDirectories();
          }
          
          private function ensureSyncthingDirectories()
          {
              $paths = [
                  config('syncthing.clients_path'),
                  config('syncthing.documents_path'),
                  config('syncthing.warehouse_path'),
                  config('syncthing.templates_path'),
                  config('syncthing.prototypes_path'),
              ];
              
              foreach ($paths as $path) {
                  if ($path && !is_dir($path)) {
                      mkdir($path, 0755, true);
                  }
              }
          }
      }
    </syncthing_service_provider>

    <syncthing_config>
      # config/syncthing.php
      <?php
      return [
          'root_path' => env('SYNCTHING_ROOT_PATH', storage_path('app/syncthing')),
          'clients_path' => env('SYNCTHING_CLIENTS_PATH', storage_path('app/syncthing/clients')),
          'documents_path' => env('SYNCTHING_DOCUMENTS_PATH', storage_path('app/syncthing/documents')),
          'warehouse_path' => env('SYNCTHING_WAREHOUSE_PATH', storage_path('app/syncthing/warehouse')),
          'templates_path' => env('SYNCTHING_TEMPLATES_PATH', storage_path('app/syncthing/templates')),
          'prototypes_path' => env('SYNCTHING_PROTOTYPES_PATH', storage_path('app/syncthing/prototypes')),
          
          'client_folders' => [
              'Documenti di Trasporto',
              'Fattura',
              'Loghi',
              'NDA',
              'Offerta',
              'Ordini Magazzino',
              'Preventivi',
              'Prototipi',
          ],
      ];
    </syncthing_config>

    <path_manager_service>
      # app/Services/SyncthingPathManager.php
      <?php
      namespace App\Services;
      
      class SyncthingPathManager
      {
          public function getClientPath(string $clientName): string
          {
              return config('syncthing.clients_path') . DIRECTORY_SEPARATOR . $clientName;
          }
          
          public function getClientDocumentPath(string $clientName, string $documentType): string
          {
              return $this->getClientPath($clientName) . DIRECTORY_SEPARATOR . $documentType;
          }
          
          public function ensureClientDirectories(string $clientName): void
          {
              $clientPath = $this->getClientPath($clientName);
              
              if (!is_dir($clientPath)) {
                  mkdir($clientPath, 0755, true);
              }
              
              foreach (config('syncthing.client_folders') as $folder) {
                  $folderPath = $clientPath . DIRECTORY_SEPARATOR . $folder;
                  if (!is_dir($folderPath)) {
                      mkdir($folderPath, 0755, true);
                  }
              }
          }
          
          public function getRelativePath(string $absolutePath): string
          {
              $rootPath = config('syncthing.root_path');
              return str_replace($rootPath . DIRECTORY_SEPARATOR, '', $absolutePath);
          }
      }
    </path_manager_service>

    <env_production_example>
      # .env.production (esempio per VPS OVH)
      APP_NAME="Supernova Management"
      APP_ENV=production
      APP_KEY=base64:your-key-here
      APP_DEBUG=false
      APP_URL=https://supernova.yourdomain.com
      
      # Database
      DB_CONNECTION=pgsql
      DB_HOST=localhost
      DB_PORT=5432
      DB_DATABASE=supernova
      DB_USERNAME=supernova
      DB_PASSWORD=secure_password_here
      
      # File System Paths (Linux Production)
      SYNCTHING_ROOT_PATH=/opt/supernova-data
      SYNCTHING_CLIENTS_PATH=/opt/supernova-data/Clienti
      SYNCTHING_DOCUMENTS_PATH=/opt/supernova-data/Documenti SRL
      SYNCTHING_WAREHOUSE_PATH=/opt/supernova-data/Magazzino
      SYNCTHING_TEMPLATES_PATH=/opt/supernova-data/Modelli Documenti
      SYNCTHING_PROTOTYPES_PATH=/opt/supernova-data/Prototipi
      
      # Cache & Session
      CACHE_DRIVER=redis
      SESSION_DRIVER=redis
      QUEUE_CONNECTION=redis
      
      # Redis
      REDIS_HOST=127.0.0.1
      REDIS_PASSWORD=null
      REDIS_PORT=6379
      
      # Mail
      MAIL_MAILER=smtp
      MAIL_HOST=smtp.ovh.net
      MAIL_PORT=587
      MAIL_USERNAME=your-email@yourdomain.com
      MAIL_PASSWORD=your-email-password
      MAIL_ENCRYPTION=tls
    </env_production_example>
  </files_configurazione_modulare>
    Crea la configurazione Docker completa:

    <dockerfile_php>
      FROM php:8.3-fpm
      
      # Install system dependencies
      RUN apt-get update && apt-get install -y \
          git curl libpng-dev libonig-dev libxml2-dev zip unzip \
          libpq-dev libzip-dev
      
      # Install PHP extensions
      RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd zip
      
      # Install Composer
      COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
      
      # Set working directory
      WORKDIR /var/www/html
      
      # Copy application code
      COPY . .
      
      # Install dependencies
      RUN composer install --no-dev --optimize-autoloader
      
      # Set permissions
      RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
    </dockerfile_php>

    <docker_compose>
      version: '3.8'
      
      services:
        app:
          build:
            context: .
            dockerfile: docker/php/Dockerfile
          container_name: supernova-app
          volumes:
            - .:/var/www/html
            - ./docker/php/php.ini:/usr/local/etc/php/conf.d/custom.ini
            # Mount Syncthing directories (configurable via .env)
            - ${SYNCTHING_ROOT_PATH:-./storage/syncthing}:/app/syncthing-data
          depends_on:
            - postgres
            - redis
          environment:
            - SYNCTHING_ROOT_PATH=/app/syncthing-data
          networks:
            - supernova-network
      
        nginx:
          image: nginx:alpine
          container_name: supernova-nginx
          ports:
            - "8080:80"
          volumes:
            - .:/var/www/html
            - ./docker/nginx/nginx.conf:/etc/nginx/conf.d/default.conf
          depends_on:
            - app
          networks:
            - supernova-network
      
        postgres:
          image: postgres:15
          container_name: supernova-postgres
          environment:
            POSTGRES_DB: supernova
            POSTGRES_USER: supernova
            POSTGRES_PASSWORD: password
          volumes:
            - postgres_data:/var/lib/postgresql/data
          ports:
            - "5432:5432"
          networks:
            - supernova-network
      
        redis:
          image: redis:alpine
          container_name: supernova-redis
          ports:
            - "6379:6379"
          networks:
            - supernova-network
      
        meilisearch:
          image: getmeili/meilisearch:latest
          container_name: supernova-meilisearch
          ports:
            - "7700:7700"
          environment:
            MEILI_MASTER_KEY: supernova_search_key
          volumes:
            - meilisearch_data:/meili_data
          networks:
            - supernova-network
      
        mailpit:
          image: axllent/mailpit
          container_name: supernova-mailpit
          ports:
            - "1025:1025"
            - "8025:8025"
          networks:
            - supernova-network
      
      volumes:
        postgres_data:
        meilisearch_data:
      
      networks:
        supernova-network:
          driver: bridge
    </docker_compose>
  </docker_configuration>

  <comandi_sviluppo>
    Dopo il setup iniziale, usa questi comandi per lo sviluppo:

    <avvio_ambiente>
      # Avvia tutti i container
      docker-compose up -d
      
      # Esegui migration
      docker-compose exec app php artisan migrate
      
      # Seed database
      docker-compose exec app php artisan db:seed
      
      # Genera storage link
      docker-compose exec app php artisan storage:link
      
      # Compila assets
      npm run dev
    </avvio_ambiente>

    <generazione_codice>
      # Crea Model con migration, factory, seeder
      php artisan make:model Component -mfs
      
      # Crea Filament Resource
      php artisan make:filament-resource Component --generate
      
      # Crea Job per queue
      php artisan make:job SyncComponentsFromSupplier
      
      # Crea Policy per autorizzazioni
      php artisan make:policy ComponentPolicy --model=Component
      
      # Crea Seeder
      php artisan make:seeder ComponentSeeder
    </generazione_codice>

    <testing>
      # Esegui tutti i test
      php artisan test
      
      # Test specifici
      php artisan test --filter ComponentTest
      
      # Test con coverage
      php artisan test --coverage
    </testing>
  </comandi_sviluppo>

  <logging_progresso>
    Per ogni sessione di sviluppo e ogni modifica significativa:
    1. Aggiorna il file "supernova_development_log.md" con formato Laravel-specific
    2. Registra migrations eseguite, resources create, jobs implementati
    3. Documenta eventuali problemi con Docker o Filament
    4. Traccia i progressi per ogni modulo sviluppato
  </logging_progresso>

  <continuità_sessioni>
    Quando l'utente dice "ci vediamo domani":
    1. Salva stato di tutte le migrations
    2. Documenta resources Filament completati
    3. Lista i prossimi comandi Artisan da eseguire
    4. Crea "supernova_next_session_prompt.md" con:
       - Stato attuale di ogni modulo
       - Migrations da eseguire
       - Resources da completare
       - Docker services status
       - Test da implementare
  </continuità_sessioni>

  <obiettivi_prima_sessione>
    Nella prima sessione, completa:
    
    1. **Setup completo environment-agnostic**:
       - Laravel + Filament + Docker con configurazione modulare
       - Environment variables per tutti i percorsi
       - Sistema di storage disks configurabile
       - Service provider per gestione percorsi Syncthing
    
    2. **Configurazione database PostgreSQL**:
       - Container Docker con volume persistente
       - Migrations base per sistema multi-tenant
    
    3. **Primo modulo inventario modulare**:
       - Migrations per categories, suppliers, components, inventory_movements
       - Models Eloquent con relationships
       - Filament Resources con path management configurabile
       - Service classes per integrazione filesystem
       - Seeder con dati di test
    
    4. **Sistema file management**:
       - Storage disks configurabili per Syncthing
       - Path manager service per gestione percorsi
       - Fallback graceful se cartelle Syncthing non esistono
    
    5. **Dashboard widget modulare**:
       - Widget per stock levels con path configurabile
       - Dashboard responsivo Filament
    
    6. **Test sistema completo**:
       - Verifica funzionamento con Docker
       - Test cambio environment variables
       - Verifica portabilità configurazione
    
    7. **Deploy locale funzionante**:
       - Sistema pronto per sviluppo
       - Documentazione path configuration
       - Script per setup rapido ambienti diversi
  </obiettivi_prima_sessione>

  <deliverable_finale>
    Il sistema completo dovrà includere:
    
    - Tutti i 9 moduli completamente funzionanti
    - Docker Compose per deploy immediato
    - Database seeded con dati di esempio
    - Dashboard Filament completo con tutti i widget
    - API endpoints per integrazioni esterne
    - Sistema di permissions e ruoli
    - Documentazione completa
    - Test coverage > 80%
    - Sistema pronto per deploy su VPS OVH
  </deliverable_finale>
</progetto>
```