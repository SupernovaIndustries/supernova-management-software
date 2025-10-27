# Nextcloud Integration - Validation & Testing

## Pre-flight Checklist

### 1. Environment Configuration
- [ ] Added Nextcloud credentials to `.env`:
  ```env
  NEXTCLOUD_URL=https://supernova-cloud.tailce6599.ts.net
  NEXTCLOUD_USERNAME=admin
  NEXTCLOUD_PASSWORD=your_password
  NEXTCLOUD_BASE_PATH=/admin/files
  ```
- [ ] Run `php artisan config:clear`
- [ ] Run `php artisan optimize:clear`

### 2. Database Migrations
Run the following SQL (or create Laravel migrations):

```sql
-- Customers table
ALTER TABLE customers ADD COLUMN IF NOT EXISTS billing_email VARCHAR(255);
ALTER TABLE customers ADD COLUMN IF NOT EXISTS billing_contact_name VARCHAR(255);
ALTER TABLE customers ADD COLUMN IF NOT EXISTS billing_phone VARCHAR(50);
ALTER TABLE customers ADD COLUMN IF NOT EXISTS default_payment_terms VARCHAR(100);
ALTER TABLE customers ADD COLUMN IF NOT EXISTS credit_limit DECIMAL(12,2);
ALTER TABLE customers ADD COLUMN IF NOT EXISTS current_balance DECIMAL(12,2) DEFAULT 0;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS nextcloud_folder_created BOOLEAN DEFAULT FALSE;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS nextcloud_base_path TEXT;

-- Projects table
ALTER TABLE projects ADD COLUMN IF NOT EXISTS nextcloud_folder_created BOOLEAN DEFAULT FALSE;
ALTER TABLE projects ADD COLUMN IF NOT EXISTS nextcloud_base_path TEXT;
ALTER TABLE projects ADD COLUMN IF NOT EXISTS components_tracked BOOLEAN DEFAULT TRUE;
ALTER TABLE projects ADD COLUMN IF NOT EXISTS total_components_cost DECIMAL(12,2) DEFAULT 0;

-- Invoices tables (if columns don't exist)
ALTER TABLE invoices_issued ADD COLUMN IF NOT EXISTS nextcloud_path TEXT;
ALTER TABLE invoices_issued ADD COLUMN IF NOT EXISTS pdf_generated_at TIMESTAMP;
ALTER TABLE invoices_received ADD COLUMN IF NOT EXISTS nextcloud_path TEXT;
```

### 3. Dependencies Check
- [ ] GuzzleHTTP is installed: `composer require guzzlehttp/guzzle` (should already be installed)
- [ ] PDF library (optional, for invoice PDF generation): `composer require barryvdh/laravel-dompdf`

---

## Validation Tests

### Test 1: Customer Folder Creation

```bash
php artisan tinker
```

```php
// Test customer creation
$customer = \App\Models\Customer::create([
    'code' => 'C999999',
    'company_name' => 'Test Nextcloud Integration SRL',
    'vat_number' => 'IT99999999999',
    'email' => 'test@example.com',
    'billing_email' => 'billing@example.com',
    'billing_contact_name' => 'Mario Rossi',
    'default_payment_terms' => '30 giorni',
    'credit_limit' => 50000.00,
]);

// Check the result
echo "Customer ID: " . $customer->id . "\n";
echo "Nextcloud Folder Created: " . ($customer->nextcloud_folder_created ? 'YES' : 'NO') . "\n";
echo "Nextcloud Base Path: " . $customer->nextcloud_base_path . "\n";
```

**Expected Result:**
- `nextcloud_folder_created` = true
- `nextcloud_base_path` = "Clienti/C999999 - Test Nextcloud Integration SRL"
- Check logs: `tail -f storage/logs/laravel.log` for success message

**Verify on Nextcloud:**
1. Login to Nextcloud: https://supernova-cloud.tailce6599.ts.net
2. Navigate to: `Clienti/C999999 - Test Nextcloud Integration SRL/`
3. Verify folder structure:
   - ✅ `_customer_info.json` file exists
   - ✅ `01_Anagrafica/` with subfolders
   - ✅ `02_Comunicazioni/` with subfolders
   - ✅ `03_Progetti/`
   - ✅ `04_Fatturazione/` with subfolders

---

### Test 2: Project Folder Creation

```php
// Continue in tinker
$project = \App\Models\Project::create([
    'code' => 'TEST-NC-001',
    'name' => 'Nextcloud Integration Test Project',
    'customer_id' => $customer->id,
    'start_date' => now(),
    'status' => 'planning',
]);

// Check the result
echo "Project ID: " . $project->id . "\n";
echo "Nextcloud Folder Created: " . ($project->nextcloud_folder_created ? 'YES' : 'NO') . "\n";
echo "Nextcloud Base Path: " . $project->nextcloud_base_path . "\n";
```

**Expected Result:**
- `nextcloud_folder_created` = true
- `nextcloud_base_path` = "Clienti/C999999 - Test Nextcloud Integration SRL/03_Progetti/TEST-NC-001"

**Verify on Nextcloud:**
1. Navigate to project folder: `Clienti/C999999.../03_Progetti/TEST-NC-001/`
2. Verify ALL folders exist:
   - ✅ `_project_info.json`
   - ✅ `_components_used.json`
   - ✅ `01_Preventivi/Bozze/`, `Inviati/`, `Accettati/`
   - ✅ `02_Progettazione/KiCad/libraries/symbols/`, `footprints/`, `3d_models/`
   - ✅ **CRITICAL**: `02_Progettazione/KiCad/SystemEngineering/Architecture/`, `Requirements/`, `Specifications/`, `Testing/`, `Validation/`
   - ✅ `02_Progettazione/Gerber/`, `BOM/`, `3D_Models/`, `Datasheet/`, `Firmware/`, `Mechanical/`
   - ✅ `03_Produzione/Ordini_PCB/`, `Ordini_Componenti/`, etc.
   - ✅ `04_Certificazioni_Conformita/CE_Marking/`, `RoHS/`, etc.
   - ✅ `05_Documentazione/User_Manuals/IT/`, `EN/`, etc.
   - ✅ `06_Consegna/DDT/`, `Packing_Lists/`, etc.
   - ✅ `07_Assistenza/Reclami/`, `RMA/`, etc.

---

### Test 3: JSON Files Content

**Check `_customer_info.json`:**
```bash
# From Nextcloud or via WebDAV
curl -u admin:password \
  "https://supernova-cloud.tailce6599.ts.net/remote.php/dav/files/admin/Clienti/C999999%20-%20Test%20Nextcloud%20Integration%20SRL/_customer_info.json"
```

Expected content:
```json
{
  "customer_code": "C999999",
  "company_name": "Test Nextcloud Integration SRL",
  "vat_number": "IT99999999999",
  "billing_email": "billing@example.com",
  "billing_contact": "Mario Rossi",
  "payment_terms": "30 giorni",
  "credit_limit": 50000.00,
  "created_at": "...",
  "nextcloud_folder_created_at": "..."
}
```

**Check `_project_info.json`:**
Expected content:
```json
{
  "project_code": "TEST-NC-001",
  "project_name": "Nextcloud Integration Test Project",
  "customer_code": "C999999",
  "customer_name": "Test Nextcloud Integration SRL",
  "status": "planning",
  "start_date": "...",
  "total_boards_ordered": 0,
  "boards_produced": 0,
  "boards_assembled": 0,
  "created_at": "..."
}
```

---

### Test 4: Observer Logs

Check Laravel logs for successful operations:

```bash
tail -50 storage/logs/laravel.log
```

**Expected log entries:**
```
[...] local.INFO: Customer folder structure created: Clienti/C999999 - Test Nextcloud Integration SRL
[...] local.INFO: Nextcloud folder created for customer: C999999
[...] local.INFO: Project folder structure created: Clienti/.../03_Progetti/TEST-NC-001
[...] local.INFO: Nextcloud folder created for project: TEST-NC-001
```

---

### Test 5: Invoice Upload (Optional)

If InvoiceIssued model exists:

```php
$invoice = \App\Models\InvoiceIssued::create([
    'invoice_number' => 'TEST-001-2025',
    'customer_id' => $customer->id,
    'issue_date' => now(),
    'due_date' => now()->addDays(30),
    'subtotal' => 1000.00,
    'tax_rate' => 22.00,
    'tax_amount' => 220.00,
    'total' => 1220.00,
    'status' => 'draft',
]);

// Update to sent to trigger PDF generation
$invoice->update(['status' => 'sent']);

echo "Nextcloud Path: " . $invoice->nextcloud_path . "\n";
```

**Verify on Nextcloud:**
- PDF should be in: `Clienti/C999999.../04_Fatturazione/Fatture_Emesse/2025/TEST-001-2025.pdf`

---

## Troubleshooting

### Issue: Folders not created
**Check:**
1. Nextcloud credentials in `.env` are correct
2. Nextcloud is accessible from the app server
3. User has write permissions
4. Check logs: `tail -f storage/logs/laravel.log`

**Solution:**
```bash
# Test connection
php artisan tinker
$helper = new \App\Helpers\NextcloudHelper();
$helper->createDirectory('Test/Folder');
$helper->listFiles('/');
```

### Issue: Observer not firing
**Check:**
1. EventServiceProvider is registered in `bootstrap/providers.php`
2. Cache is cleared: `php artisan optimize:clear`
3. Observers are registered in EventServiceProvider

**Solution:**
```bash
php artisan optimize:clear
php artisan config:clear
php artisan event:clear
```

### Issue: Permission denied on Nextcloud
**Check:**
1. NFS permissions on NAS
2. Auto-sync script is running
3. www-data user ownership

**Solution:**
Wait 5 minutes for auto-sync to fix permissions, or run manually:
```bash
# On Mac server
/Users/supernova/Cloud/scan-nas.sh
```

---

## Performance Check

Monitor observer execution time:

```bash
# In tinker, with timing
$start = microtime(true);
$customer = \App\Models\Customer::create([...]);
$end = microtime(true);
echo "Time taken: " . round($end - $start, 2) . " seconds\n";
```

**Expected:**
- Customer creation: < 5 seconds
- Project creation: < 10 seconds (more folders)

If slower, consider:
1. Move folder creation to a background job
2. Use Laravel queues for large operations

---

## Cleanup Test Data

After validation:

```php
// Delete test customer (will cascade to projects)
$customer = \App\Models\Customer::where('code', 'C999999')->first();
$customer->delete();
```

Manually remove folders from Nextcloud:
- Delete: `Clienti/C999999 - Test Nextcloud Integration SRL/`

---

## Validation Checklist

- [ ] Environment configured
- [ ] Database migrations run
- [ ] Customer folder structure created correctly
- [ ] Project folder structure created with ALL subfolders
- [ ] SystemEngineering folder exists with all subfolders
- [ ] KiCad/libraries folder exists with symbols/, footprints/, 3d_models/
- [ ] JSON files generated correctly
- [ ] Observers logged successful operations
- [ ] No errors in Laravel logs
- [ ] Invoice PDF upload works (if applicable)
- [ ] Test data cleaned up

---

## Status

**Validation Status**: ⏳ PENDING

Complete all checklist items above to mark as: ✅ VALIDATED

---

## Support

For issues:
1. Check `storage/logs/laravel.log`
2. Verify Nextcloud is accessible
3. Review NEXTCLOUD-IMPLEMENTATION-SUMMARY.md
4. Review LARAVEL-NEXTCLOUD-INTEGRATION.md

**Note**: The auto-sync system on Nextcloud runs every 5 minutes. If files don't appear immediately, wait 5 minutes and check again.
