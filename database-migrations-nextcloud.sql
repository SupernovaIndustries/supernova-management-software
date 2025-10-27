-- Database Migrations for Nextcloud Integration
-- Run these SQL commands to add required fields

-- ============================================
-- CUSTOMERS TABLE
-- ============================================

-- Add billing information fields
ALTER TABLE customers ADD COLUMN IF NOT EXISTS billing_email VARCHAR(255);
ALTER TABLE customers ADD COLUMN IF NOT EXISTS billing_contact_name VARCHAR(255);
ALTER TABLE customers ADD COLUMN IF NOT EXISTS billing_phone VARCHAR(50);
ALTER TABLE customers ADD COLUMN IF NOT EXISTS default_payment_terms VARCHAR(100);
ALTER TABLE customers ADD COLUMN IF NOT EXISTS credit_limit DECIMAL(12,2);
ALTER TABLE customers ADD COLUMN IF NOT EXISTS current_balance DECIMAL(12,2) DEFAULT 0;

-- Add Nextcloud tracking fields
ALTER TABLE customers ADD COLUMN IF NOT EXISTS nextcloud_folder_created BOOLEAN DEFAULT FALSE;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS nextcloud_base_path TEXT;

-- ============================================
-- PROJECTS TABLE
-- ============================================

-- Add Nextcloud tracking fields
ALTER TABLE projects ADD COLUMN IF NOT EXISTS nextcloud_folder_created BOOLEAN DEFAULT FALSE;
ALTER TABLE projects ADD COLUMN IF NOT EXISTS nextcloud_base_path TEXT;

-- Add component tracking fields
ALTER TABLE projects ADD COLUMN IF NOT EXISTS components_tracked BOOLEAN DEFAULT TRUE;
ALTER TABLE projects ADD COLUMN IF NOT EXISTS total_components_cost DECIMAL(12,2) DEFAULT 0;

-- ============================================
-- INVOICES_ISSUED TABLE
-- ============================================

-- Add Nextcloud path tracking (if not exists)
ALTER TABLE invoices_issued ADD COLUMN IF NOT EXISTS nextcloud_path TEXT;
ALTER TABLE invoices_issued ADD COLUMN IF NOT EXISTS pdf_generated_at TIMESTAMP;

-- ============================================
-- INVOICES_RECEIVED TABLE
-- ============================================

-- Add Nextcloud path tracking (if not exists)
ALTER TABLE invoices_received ADD COLUMN IF NOT EXISTS nextcloud_path TEXT;

-- ============================================
-- VERIFY MIGRATIONS
-- ============================================

-- Check customers table
SELECT column_name, data_type, is_nullable
FROM information_schema.columns
WHERE table_name = 'customers'
AND column_name IN ('billing_email', 'billing_contact_name', 'nextcloud_folder_created', 'nextcloud_base_path')
ORDER BY column_name;

-- Check projects table
SELECT column_name, data_type, is_nullable
FROM information_schema.columns
WHERE table_name = 'projects'
AND column_name IN ('nextcloud_folder_created', 'nextcloud_base_path', 'components_tracked', 'total_components_cost')
ORDER BY column_name;

-- Check invoices_issued table
SELECT column_name, data_type, is_nullable
FROM information_schema.columns
WHERE table_name = 'invoices_issued'
AND column_name IN ('nextcloud_path', 'pdf_generated_at')
ORDER BY column_name;

-- Check invoices_received table
SELECT column_name, data_type, is_nullable
FROM information_schema.columns
WHERE table_name = 'invoices_received'
AND column_name IN ('nextcloud_path')
ORDER BY column_name;
