# Nextcloud Environment Configuration

Add these environment variables to your `.env` file:

```env
# Nextcloud Integration
NEXTCLOUD_URL=https://supernova-cloud.tailce6599.ts.net
NEXTCLOUD_USERNAME=admin
NEXTCLOUD_PASSWORD=your_nextcloud_admin_password
NEXTCLOUD_BASE_PATH=/admin/files
```

## How It Works

Once configured, the system will automatically:

1. **Customer Creation**: When a new customer is created, the system will:
   - Create a complete folder structure on Nextcloud
   - Generate `_customer_info.json` with billing information
   - Update `customer.nextcloud_base_path` and `customer.nextcloud_folder_created = true`

2. **Project Creation**: When a new project is created, the system will:
   - Create a complete folder structure including SystemEngineering and KiCad/libraries
   - Generate `_project_info.json`
   - Generate `_components_used.json` (initially empty)
   - Update `project.nextcloud_base_path` and `project.nextcloud_folder_created = true`

3. **Invoice Issued**: When an invoice is issued and PDF is generated:
   - Upload to: `Clienti/{CUSTOMER}/04_Fatturazione/Fatture_Emesse/{YEAR}/{INVOICE_NUMBER}.pdf`
   - Update `invoice.nextcloud_path`

4. **Invoice Received**: When an invoice is received:
   - For components: Upload to `Magazzino/Fatture_Magazzino/Fornitori/{YEAR}/`
   - For customs: Upload to `Magazzino/Fatture_Magazzino/Dogana/{YEAR}/`
   - For equipment: Upload to `Magazzino/Fatture_Magazzino/Macchinari/{YEAR}/`
   - Generate `{SUPPLIER}_{DATE}_components.json` if components invoice

5. **Component Allocation**: When components are allocated to a project:
   - Update `_components_used.json` in project root
   - Create `_components_allocation.json` in `Ordini_Componenti` folder
   - Update `project.total_components_cost`

## Folder Structure Created

### Customer Folder
```
Clienti/{CODE} - {NAME}/
├── _customer_info.json
├── 01_Anagrafica/
│   ├── Visura_Camerale/
│   ├── Documenti_Identita/
│   ├── Certificazioni/
│   └── Contratti/
├── 02_Comunicazioni/
│   ├── Email/
│   ├── Lettere/
│   └── Verbali_Riunioni/
├── 03_Progetti/
└── 04_Fatturazione/
    ├── Fatture_Emesse/{YEAR}/
    ├── Fatture_Ricevute/{YEAR}/
    ├── F24/{YEAR}/
    ├── Note_Credito/{YEAR}/
    └── Pagamenti/
        ├── Ricevute/
        └── Bonifici/
```

### Project Folder (inside customer folder)
```
03_Progetti/{PROJECT_CODE}/
├── _project_info.json
├── _components_used.json
├── 01_Preventivi/
│   ├── Bozze/
│   ├── Inviati/
│   └── Accettati/
├── 02_Progettazione/
│   ├── KiCad/
│   │   ├── libraries/
│   │   │   ├── symbols/
│   │   │   ├── footprints/
│   │   │   └── 3d_models/
│   │   └── SystemEngineering/
│   │       ├── Architecture/
│   │       ├── Requirements/
│   │       ├── Specifications/
│   │       ├── Testing/
│   │       └── Validation/
│   ├── Gerber/{VERSION}/
│   ├── BOM/
│   ├── 3D_Models/
│   ├── Datasheet/
│   ├── Firmware/{VERSION}/
│   └── Mechanical/
├── 03_Produzione/
│   ├── Ordini_PCB/
│   ├── Ordini_Componenti/
│   ├── Assembly_Instructions/
│   ├── Test_Reports/
│   └── Production_Logs/
├── 04_Certificazioni_Conformita/
├── 05_Documentazione/
├── 06_Consegna/
└── 07_Assistenza/
```

## Database Migrations Required

Run these migrations to add the necessary fields:

```sql
-- Add to customers table
ALTER TABLE customers ADD COLUMN billing_email VARCHAR(255);
ALTER TABLE customers ADD COLUMN billing_contact_name VARCHAR(255);
ALTER TABLE customers ADD COLUMN billing_phone VARCHAR(50);
ALTER TABLE customers ADD COLUMN default_payment_terms VARCHAR(100);
ALTER TABLE customers ADD COLUMN credit_limit DECIMAL(12,2);
ALTER TABLE customers ADD COLUMN current_balance DECIMAL(12,2) DEFAULT 0;
ALTER TABLE customers ADD COLUMN nextcloud_folder_created BOOLEAN DEFAULT FALSE;
ALTER TABLE customers ADD COLUMN nextcloud_base_path TEXT;

-- Add to projects table
ALTER TABLE projects ADD COLUMN nextcloud_folder_created BOOLEAN DEFAULT FALSE;
ALTER TABLE projects ADD COLUMN nextcloud_base_path TEXT;
ALTER TABLE projects ADD COLUMN components_tracked BOOLEAN DEFAULT TRUE;
ALTER TABLE projects ADD COLUMN total_components_cost DECIMAL(12,2) DEFAULT 0;

-- Add to invoices_issued table (if needed)
ALTER TABLE invoices_issued ADD COLUMN nextcloud_path TEXT;
ALTER TABLE invoices_issued ADD COLUMN pdf_generated_at TIMESTAMP;

-- Add to invoices_received table (if needed)
ALTER TABLE invoices_received ADD COLUMN nextcloud_path TEXT;
```

## Testing

After configuration:

1. **Test Customer Creation**:
   ```php
   $customer = Customer::create([
       'code' => 'C000001',
       'company_name' => 'ACME SRL',
       'vat_number' => 'IT12345678901',
       'billing_email' => 'billing@acme.it',
   ]);

   // Check Nextcloud for folder structure
   // Check logs for success messages
   ```

2. **Test Project Creation**:
   ```php
   $project = Project::create([
       'code' => 'ACME-IOT',
       'name' => 'IoT Sensor',
       'customer_id' => $customer->id,
   ]);

   // Verify all subfolders created including SystemEngineering
   ```

## Troubleshooting

1. **Folders not created**: Check Nextcloud credentials in `.env`
2. **Permission errors**: Ensure Nextcloud user has write permissions
3. **JSON not uploaded**: Check logs in `storage/logs/laravel.log`
4. **Observer not firing**: Run `php artisan optimize:clear`

## Important Notes

- All Nextcloud operations are logged for debugging
- Errors don't break the application flow (fail gracefully)
- PDF generation requires `barryvdh/laravel-dompdf` package
- Nextcloud auto-sync runs every 5 minutes to fix permissions
