# DATABASE SCHEMA - Sistema Gestionale Completo

## 📊 SCHEMA COMPLETO

### 1. FATTURE EMESSE (Invoices Issued)

```sql
CREATE TABLE invoices_issued (
    id BIGSERIAL PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    incremental_id INTEGER NOT NULL,
    customer_id BIGINT REFERENCES customers(id) ON DELETE RESTRICT,
    project_id BIGINT REFERENCES projects(id) ON DELETE SET NULL,
    quotation_id BIGINT REFERENCES quotations(id) ON DELETE SET NULL,

    -- Tipo fattura
    type VARCHAR(50) NOT NULL DEFAULT 'standard', -- standard, advance_payment, balance, credit_note

    -- Dati fattura
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    payment_term_id BIGINT REFERENCES payment_terms(id),

    -- Importi
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 22.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) NOT NULL,

    -- Pagamento progressivo (es: 30% + 70%)
    payment_stage VARCHAR(50), -- 'deposit', 'balance', 'full'
    payment_percentage DECIMAL(5,2), -- 30.00, 70.00, 100.00
    related_invoice_id BIGINT REFERENCES invoices_issued(id), -- Link a fattura correlata (es: saldo collegato ad acconto)

    -- Stati
    status VARCHAR(50) NOT NULL DEFAULT 'draft', -- draft, sent, paid, overdue, cancelled
    payment_status VARCHAR(50) NOT NULL DEFAULT 'unpaid', -- unpaid, partial, paid

    -- Pagamenti
    amount_paid DECIMAL(12,2) DEFAULT 0,
    paid_at TIMESTAMP,
    payment_method VARCHAR(50),

    -- Nextcloud
    nextcloud_path TEXT,
    pdf_generated_at TIMESTAMP,

    -- Note
    notes TEXT,
    internal_notes TEXT,

    -- Metadati
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_invoices_issued_customer ON invoices_issued(customer_id);
CREATE INDEX idx_invoices_issued_project ON invoices_issued(project_id);
CREATE INDEX idx_invoices_issued_date ON invoices_issued(issue_date);
CREATE INDEX idx_invoices_issued_status ON invoices_issued(status);
```

### 2. RIGHE FATTURE EMESSE

```sql
CREATE TABLE invoice_issued_items (
    id BIGSERIAL PRIMARY KEY,
    invoice_id BIGINT NOT NULL REFERENCES invoices_issued(id) ON DELETE CASCADE,

    -- Descrizione
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    discount_percentage DECIMAL(5,2) DEFAULT 0,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 22.00,

    -- Totali
    subtotal DECIMAL(12,2) NOT NULL,
    tax_amount DECIMAL(12,2) NOT NULL,
    total DECIMAL(12,2) NOT NULL,

    -- Link opzionali
    component_id BIGINT REFERENCES components(id) ON DELETE SET NULL,
    project_bom_item_id BIGINT REFERENCES project_bom_items(id) ON DELETE SET NULL,

    -- Ordinamento
    sort_order INTEGER DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_invoice_issued_items_invoice ON invoice_issued_items(invoice_id);
```

### 3. FATTURE RICEVUTE (Invoices Received)

```sql
CREATE TABLE invoices_received (
    id BIGSERIAL PRIMARY KEY,
    invoice_number VARCHAR(100) NOT NULL,

    -- Fornitore
    supplier_id BIGINT REFERENCES suppliers(id) ON DELETE RESTRICT,
    supplier_name VARCHAR(255) NOT NULL,
    supplier_vat VARCHAR(50),

    -- Tipo e categoria
    type VARCHAR(50) NOT NULL DEFAULT 'purchase', -- purchase, customs, equipment, general, restock
    category VARCHAR(50) NOT NULL DEFAULT 'components', -- components, equipment, services, customs, general

    -- Link a progetto/cliente (se fattura per progetto specifico)
    project_id BIGINT REFERENCES projects(id) ON DELETE SET NULL,
    customer_id BIGINT REFERENCES customers(id) ON DELETE SET NULL,

    -- Dati fattura
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    received_date DATE,

    -- Importi
    subtotal DECIMAL(12,2) NOT NULL,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',

    -- Pagamento
    payment_status VARCHAR(50) NOT NULL DEFAULT 'unpaid',
    amount_paid DECIMAL(12,2) DEFAULT 0,
    paid_at TIMESTAMP,
    payment_method VARCHAR(50),

    -- Nextcloud
    nextcloud_path TEXT,

    -- Note
    notes TEXT,

    -- Metadati
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_invoices_received_supplier ON invoices_received(supplier_id);
CREATE INDEX idx_invoices_received_project ON invoices_received(project_id);
CREATE INDEX idx_invoices_received_type ON invoices_received(type);
CREATE INDEX idx_invoices_received_date ON invoices_received(issue_date);
```

### 4. RIGHE FATTURE RICEVUTE

```sql
CREATE TABLE invoice_received_items (
    id BIGSERIAL PRIMARY KEY,
    invoice_id BIGINT NOT NULL REFERENCES invoices_received(id) ON DELETE CASCADE,

    description TEXT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    tax_rate DECIMAL(5,2) DEFAULT 22.00,

    subtotal DECIMAL(12,2) NOT NULL,
    tax_amount DECIMAL(12,2) NOT NULL,
    total DECIMAL(12,2) NOT NULL,

    -- Link a componente (se acquisto componenti)
    component_id BIGINT REFERENCES components(id) ON DELETE SET NULL,

    sort_order INTEGER DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_invoice_received_items_invoice ON invoice_received_items(invoice_id);
CREATE INDEX idx_invoice_received_items_component ON invoice_received_items(component_id);
```

### 5. MAPPING FATTURE-COMPONENTI (per tracciabilità magazzino)

```sql
CREATE TABLE invoice_component_mappings (
    id BIGSERIAL PRIMARY KEY,

    -- Fattura ricevuta
    invoice_received_id BIGINT NOT NULL REFERENCES invoices_received(id) ON DELETE CASCADE,
    invoice_received_item_id BIGINT REFERENCES invoice_received_items(id) ON DELETE CASCADE,

    -- Componente
    component_id BIGINT NOT NULL REFERENCES components(id) ON DELETE RESTRICT,

    -- Quantità acquistata
    quantity DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    total_cost DECIMAL(12,2) NOT NULL,

    -- Movimento magazzino associato
    inventory_movement_id BIGINT REFERENCES inventory_movements(id) ON DELETE SET NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_invoice_component_invoice ON invoice_component_mappings(invoice_received_id);
CREATE INDEX idx_invoice_component_component ON invoice_component_mappings(component_id);
```

### 6. ALLOCAZIONE COMPONENTI A PROGETTI

```sql
CREATE TABLE project_component_allocations (
    id BIGSERIAL PRIMARY KEY,

    -- Progetto e componente
    project_id BIGINT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    component_id BIGINT NOT NULL REFERENCES components(id) ON DELETE RESTRICT,

    -- Quantità
    quantity_allocated DECIMAL(10,2) NOT NULL,
    quantity_used DECIMAL(10,2) DEFAULT 0,
    quantity_remaining DECIMAL(10,2),

    -- BOM item associato
    project_bom_item_id BIGINT REFERENCES project_bom_items(id) ON DELETE SET NULL,

    -- Stato
    status VARCHAR(50) DEFAULT 'allocated', -- allocated, in_use, completed, returned

    -- Costi
    unit_cost DECIMAL(12,2),
    total_cost DECIMAL(12,2),

    -- Tracciabilità: da quale fattura vengono questi componenti
    source_invoice_id BIGINT REFERENCES invoices_received(id) ON DELETE SET NULL,

    -- Date
    allocated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP,

    -- Note
    notes TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_project_allocations_project ON project_component_allocations(project_id);
CREATE INDEX idx_project_allocations_component ON project_component_allocations(component_id);
CREATE INDEX idx_project_allocations_status ON project_component_allocations(status);
```

### 7. CONTRATTI CLIENTI

```sql
CREATE TABLE customer_contracts (
    id BIGSERIAL PRIMARY KEY,

    customer_id BIGINT NOT NULL REFERENCES customers(id) ON DELETE CASCADE,

    contract_number VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL, -- nda, service_agreement, supply_contract, partnership

    -- Date
    start_date DATE NOT NULL,
    end_date DATE,
    signed_at DATE,

    -- Importi
    contract_value DECIMAL(12,2),
    currency VARCHAR(3) DEFAULT 'EUR',

    -- File
    nextcloud_path TEXT,
    pdf_generated_at TIMESTAMP,

    -- Stato
    status VARCHAR(50) DEFAULT 'draft', -- draft, active, expired, terminated

    -- Note
    terms TEXT,
    notes TEXT,

    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_contracts_customer ON customer_contracts(customer_id);
CREATE INDEX idx_contracts_status ON customer_contracts(status);
```

### 8. F24 (Modello F24)

```sql
CREATE TABLE f24_forms (
    id BIGSERIAL PRIMARY KEY,

    form_number VARCHAR(50) UNIQUE NOT NULL,

    -- Tipo
    type VARCHAR(50) NOT NULL, -- imu, tasi, iva, inps, inail, irpef, other

    -- Periodo di riferimento
    reference_month INTEGER,
    reference_year INTEGER NOT NULL,

    -- Importi
    total_amount DECIMAL(12,2) NOT NULL,

    -- Date
    payment_date DATE NOT NULL,
    due_date DATE,

    -- Link a cliente (se F24 per cliente specifico)
    customer_id BIGINT REFERENCES customers(id) ON DELETE SET NULL,

    -- File
    nextcloud_path TEXT,

    -- Note
    notes TEXT,

    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_f24_year ON f24_forms(reference_year);
CREATE INDEX idx_f24_customer ON f24_forms(customer_id);
```

### 9. ANALISI BILANCIO (Billing Analysis)

```sql
CREATE TABLE billing_analysis (
    id BIGSERIAL PRIMARY KEY,

    -- Periodo
    analysis_type VARCHAR(50) NOT NULL, -- monthly, quarterly, yearly, custom
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,

    -- Ricavi
    total_revenue DECIMAL(12,2) DEFAULT 0,
    total_invoiced DECIMAL(12,2) DEFAULT 0,
    total_paid DECIMAL(12,2) DEFAULT 0,
    total_outstanding DECIMAL(12,2) DEFAULT 0,

    -- Costi
    total_costs DECIMAL(12,2) DEFAULT 0,
    warehouse_costs DECIMAL(12,2) DEFAULT 0,
    equipment_costs DECIMAL(12,2) DEFAULT 0,
    service_costs DECIMAL(12,2) DEFAULT 0,
    customs_costs DECIMAL(12,2) DEFAULT 0,

    -- Profitto
    gross_profit DECIMAL(12,2) DEFAULT 0,
    net_profit DECIMAL(12,2) DEFAULT 0,
    profit_margin DECIMAL(5,2) DEFAULT 0,

    -- Previsioni
    forecasted_revenue DECIMAL(12,2) DEFAULT 0,
    forecasted_costs DECIMAL(12,2) DEFAULT 0,

    -- Dettagli JSON
    details JSONB,

    -- Generazione
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    generated_by BIGINT REFERENCES users(id),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_billing_analysis_period ON billing_analysis(period_start, period_end);
CREATE INDEX idx_billing_analysis_type ON billing_analysis(analysis_type);
```

### 10. TRACKING PAGAMENTI PROGRESSIVI

```sql
CREATE TABLE payment_milestones (
    id BIGSERIAL PRIMARY KEY,

    -- Progetto/Preventivo
    project_id BIGINT REFERENCES projects(id) ON DELETE CASCADE,
    quotation_id BIGINT REFERENCES quotations(id) ON DELETE CASCADE,

    -- Milestone
    milestone_name VARCHAR(255) NOT NULL, -- 'Acconto 30%', 'Saldo 70%', etc.
    percentage DECIMAL(5,2) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,

    -- Stato
    status VARCHAR(50) DEFAULT 'pending', -- pending, invoiced, paid

    -- Fattura associata
    invoice_id BIGINT REFERENCES invoices_issued(id) ON DELETE SET NULL,

    -- Date
    expected_date DATE,
    invoiced_at TIMESTAMP,
    paid_at TIMESTAMP,

    -- Ordinamento
    sort_order INTEGER DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_payment_milestones_project ON payment_milestones(project_id);
CREATE INDEX idx_payment_milestones_quotation ON payment_milestones(quotation_id);
CREATE INDEX idx_payment_milestones_status ON payment_milestones(status);
```

### 11. MODIFICHE TABELLA INVENTORY_MOVEMENTS

```sql
-- Aggiungi colonne per tracciabilità fatture
ALTER TABLE inventory_movements ADD COLUMN source_invoice_id BIGINT REFERENCES invoices_received(id) ON DELETE SET NULL;
ALTER TABLE inventory_movements ADD COLUMN destination_project_id BIGINT REFERENCES projects(id) ON DELETE SET NULL;
ALTER TABLE inventory_movements ADD COLUMN allocation_id BIGINT REFERENCES project_component_allocations(id) ON DELETE SET NULL;
ALTER TABLE inventory_movements ADD COLUMN unit_cost DECIMAL(12,2);
ALTER TABLE inventory_movements ADD COLUMN total_cost DECIMAL(12,2);

CREATE INDEX idx_inventory_movements_invoice ON inventory_movements(source_invoice_id);
CREATE INDEX idx_inventory_movements_project ON inventory_movements(destination_project_id);
```

### 12. INFO FATTURAZIONE CLIENTE (in tabella customers - aggiungi colonne)

```sql
-- Aggiungi info fatturazione a tabella customers esistente
ALTER TABLE customers ADD COLUMN billing_email VARCHAR(255);
ALTER TABLE customers ADD COLUMN billing_contact_name VARCHAR(255);
ALTER TABLE customers ADD COLUMN billing_phone VARCHAR(50);
ALTER TABLE customers ADD COLUMN default_payment_terms VARCHAR(100);
ALTER TABLE customers ADD COLUMN credit_limit DECIMAL(12,2);
ALTER TABLE customers ADD COLUMN current_balance DECIMAL(12,2) DEFAULT 0;
ALTER TABLE customers ADD COLUMN nextcloud_folder_created BOOLEAN DEFAULT FALSE;
ALTER TABLE customers ADD COLUMN nextcloud_base_path TEXT;
```

### 13. INFO PROGETTI (in tabella projects - aggiungi colonne)

```sql
ALTER TABLE projects ADD COLUMN nextcloud_folder_created BOOLEAN DEFAULT FALSE;
ALTER TABLE projects ADD COLUMN nextcloud_base_path TEXT;
ALTER TABLE projects ADD COLUMN components_tracked BOOLEAN DEFAULT TRUE;
ALTER TABLE projects ADD COLUMN total_components_cost DECIMAL(12,2) DEFAULT 0;
```

---

## 📋 RELATIONSHIPS OVERVIEW

### Fatturazione
- `invoices_issued` → `customers` (many-to-one)
- `invoices_issued` → `projects` (many-to-one, optional)
- `invoices_issued` → `quotations` (many-to-one, optional)
- `invoices_issued` → `payment_milestones` (one-to-many)
- `invoice_issued_items` → `invoices_issued` (many-to-one)

### Magazzino & Fatture
- `invoices_received` → `suppliers` (many-to-one)
- `invoices_received` → `projects` (many-to-one, optional)
- `invoice_received_items` → `invoices_received` (many-to-one)
- `invoice_component_mappings` → `invoices_received` + `components` (many-to-many)
- `inventory_movements` → `invoices_received` (many-to-one, optional)

### Allocazione Componenti
- `project_component_allocations` → `projects` + `components` (many-to-many)
- `project_component_allocations` → `invoices_received` (tracciabilità fonte)
- `project_component_allocations` → `inventory_movements` (riduzione stock)

### Contratti & F24
- `customer_contracts` → `customers` (many-to-one)
- `f24_forms` → `customers` (many-to-one, optional)

### Analisi
- `billing_analysis` → analisi aggregate (standalone)
- `payment_milestones` → `projects` + `quotations` (tracking pagamenti progressivi)

---

## 🔄 WORKFLOW COMPLETO

### 1. Creazione Cliente
1. Create `customer` → Observer crea cartelle Nextcloud
2. Genera `_customer_info.json` con dati fatturazione

### 2. Creazione Progetto
1. Create `project` → Observer crea cartelle Nextcloud
2. Genera `_project_info.json`
3. Genera `_components_used.json` (vuoto iniziale)

### 3. Preventivo → Fattura
1. `quotation` accettato
2. Crea `payment_milestones` (30%, 70%, etc.)
3. Genera `invoice_issued` per prima milestone
4. Upload PDF su Nextcloud: `Clienti/{CUSTOMER}/04_Fatturazione/Fatture_Emesse/{YEAR}/{INVOICE}.pdf`

### 4. Acquisto Componenti
1. Crea `invoice_received` (tipo: purchase/components)
2. Aggiungi `invoice_received_items` con componenti
3. Crea `invoice_component_mappings` per ogni componente
4. Crea `inventory_movement` (IN) linkato a fattura
5. Aggiorna stock componenti
6. Upload fattura su Nextcloud: `Magazzino/Fatture_Magazzino/Fornitori/{YEAR}/{SUPPLIER}_{DATE}.pdf`
7. Genera `{SUPPLIER}_{DATE}_components.json` con lista componenti

### 5. Allocazione Componenti a Progetto
1. Crea `project_component_allocation`
2. Crea `inventory_movement` (OUT) linkato ad allocazione
3. Riduce stock componente
4. Aggiorna `total_components_cost` progetto
5. Aggiorna `_components_used.json` progetto
6. Aggiorna `_components_allocation.json` in cartella Ordini_Componenti

### 6. Analisi Bilancio
1. Job schedulato mensile/settimanale
2. Calcola ricavi da `invoices_issued`
3. Calcola costi da `invoices_received`
4. Calcola previsioni da `payment_milestones` pendenti
5. Salva `billing_analysis`
6. Genera JSON in `Analytics/Billing/`

---

## 🎯 FEATURES CHIAVE

### Tracciabilità 100%
- Ogni fattura ricevuta → componenti acquistati
- Ogni componente → fattura di acquisto
- Ogni allocazione → progetto + fattura fonte
- Ogni movimento magazzino → fattura o allocazione

### Interlinking Completo
- Fattura magazzino ← Componenti → Progetti
- Fattura cliente ← Progetto → Componenti allocati
- Preventivo → Milestone pagamenti → Fatture emesse

### Pagamenti Progressivi
- `payment_milestones` per tracking 30%+70%
- Fattura acconto (30%) → status: invoiced
- Progetto completato → Fattura saldo (70%)
- Analisi bilancio conta solo importi fatturati

### Analisi Bilancio
- Ricavi: somma fatture emesse (solo quelle effettivamente emesse)
- Costi: somma fatture ricevute per categoria
- Previsioni: milestone pendenti + trend storico
- Mese per mese: `billing_analysis` con JSON dettagliato

### Mobile Optimization
- Filament responsive tables
- Forms mobile-friendly
- Dashboard widget ottimizzati
- Touch-friendly actions
