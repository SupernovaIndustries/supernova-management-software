# DDT (Documento di Trasporto) Implementation Summary

## Overview
Complete DDT generation system for board assemblies has been implemented. The system generates professional Italian transport documents with AI-powered descriptions, automatic field population, and Nextcloud integration.

## Files Created/Modified

### 1. Database Migration
**File**: `/database/migrations/2025_10_06_200000_add_ddt_fields_to_board_assembly_logs_table.php`
- Added 16 DDT-related fields to `board_assembly_logs` table
- Includes: number, date, transport type, delivery address, payment condition, package info, signatures, etc.
- Migration has been successfully run

### 2. DdtService
**File**: `/app/Services/DdtService.php`
- `generateDdtNumber()` - Progressive numbering (format: DDT-YYYY-NNNN)
- `generateGoodsDescription()` - AI-powered description using Claude
- `determineTransportReason()` - Auto-logic for causale
- `determinePaymentCondition()` - Auto-logic for payment (in_conto/in_saldo)
- `generateDdtPdf()` - PDF generation using DomPDF
- `uploadToNextcloud()` - Upload to project folder structure
- `generateAndUploadDdt()` - Complete workflow
- `updateSignatures()` - Add signatures and regenerate PDF

### 3. BoardAssemblyLog Model Updates
**File**: `/app/Models/BoardAssemblyLog.php`
- Added all DDT fields to `$fillable` array
- Added proper casts for dates, JSON, decimals
- New methods:
  - `hasDdt()` - Check if DDT exists
  - `isDdtSigned()` - Check if DDT is signed
  - `getDdtStatusAttribute()` - Get status (not_generated/generated/signed)
  - `getDdtStatusColorAttribute()` - Get badge color
  - `getDdtStatusLabelAttribute()` - Get status label

### 4. PDF Template
**File**: `/resources/views/pdf/ddt-assembly.blade.php`
- Professional Italian DDT template
- Includes:
  - Company header with logo placeholder
  - Customer/recipient info
  - Delivery address (can differ from customer)
  - Transport type selection (cedente/cessionario)
  - Order information
  - Goods table with AI-generated description
  - Package details (colli, weight, appearance)
  - Signature sections (conductor and recipient)
  - Legal footer
- Optimized for A4 portrait printing

### 5. Filament Integration
**File**: `/app/Filament/Resources/ProjectResource/RelationManagers/BoardAssemblyLogsRelationManager.php`

#### Form Changes:
- Converted to tabbed interface
- **Tab 1: Assemblaggio** - Original assembly form
- **Tab 2: DDT** - DDT configuration (visible only on edit/view)
  - DDT number (auto-generated)
  - DDT date
  - Transport type selection
  - Payment condition
  - Transport reason (auto-suggested)
  - Goods description (AI-generated)
  - Package details (count, weight, appearance)
  - Custom delivery address (optional)

#### Table Changes:
- Added DDT status badge column showing:
  - Gray "Non Generato" with X icon
  - Yellow "Generato" with document icon
  - Green "Firmato" with check icon

#### New Actions:
1. **Generate DDT** (visible when no DDT exists)
   - Generates DDT with auto-populated fields
   - Uses AI for goods description
   - Uploads to Nextcloud automatically

2. **View DDT** (visible when DDT exists)
   - Opens PDF in new tab

3. **Download DDT** (visible when DDT exists)
   - Downloads PDF file (signed version if available)

4. **Sign DDT** (visible when DDT generated but not signed)
   - Modal form for conductor and recipient signatures
   - Regenerates PDF with signature info
   - Uploads signed version to Nextcloud

### 6. Route Addition
**File**: `/routes/web.php`
- Added route: `/admin/projects/{project}/board-assembly-logs/{assemblyLog}/view-ddt`
- Handles inline PDF viewing in browser

## Nextcloud Storage Structure

DDT PDFs are stored in:
```
Clienti/{customer}/01_Progetti/{project}/06_Consegna/DDT/
  DDT_DDT-2025-0001_2025-10-06.pdf
  DDT_DDT-2025-0001_2025-10-06_signed.pdf
```

## DDT Auto-Logic

### Transport Reason (Causale)
- **Prototype**: "Consegna campione dispositivo elettronico per test e validazione"
- **Partial delivery**: "Consegna parziale dispositivi elettronici (X di Y totali)"
- **Complete delivery**: "Consegna dispositivi elettronici"

### Payment Condition
- **Prototype or partial**: `in_conto` (acconto)
- **Complete delivery**: `in_saldo` (saldo finale)

### Goods Description (AI-Generated)
Claude AI generates a concise, professional description based on:
- Project name
- Number of boards
- Assembly type (prototype/production)
- BOM components (if available)
- Main component categories

Example output: "Schede elettroniche per controllo motori DC con microcontrollore STM32 (3 pezzi)"

## User Workflow

1. **Create Assembly Log**
   - Fill assembly details in "Assemblaggio" tab
   - Save record

2. **Configure DDT** (optional, before generation)
   - Switch to "DDT" tab
   - Modify auto-generated fields if needed
   - Add custom delivery address if different from customer
   - Adjust package details

3. **Generate DDT**
   - Click "Genera DDT" action button
   - System automatically:
     - Generates progressive DDT number
     - Creates AI-powered goods description
     - Determines causale and payment condition
     - Generates PDF
     - Uploads to Nextcloud
   - Success notification shows DDT number

4. **View/Download DDT**
   - Click "Visualizza DDT" to view in browser
   - Click "Scarica DDT" to download file

5. **Sign DDT** (when ready)
   - Click "Firma DDT"
   - Enter conductor and recipient info
   - System regenerates PDF with signature placeholders
   - Uploads signed version to Nextcloud

## Features

### AI Integration
- Uses Claude API from CompanyProfile settings
- Generates professional Italian descriptions
- Fallback to basic description if AI unavailable
- 150 character limit for conciseness

### Progressive Numbering
- Format: DDT-YYYY-NNNN
- Year-based reset
- Automatic increment
- Manual override possible

### Validation & Safety
- All fields editable before PDF generation
- Most fields readonly after generation (except signatures)
- No duplicate DDT numbers
- Proper error handling and logging

### Multi-Language Support
- Italian labels and descriptions
- Professional terminology (cedente, cessionario, colli, etc.)
- Compliant with Italian transport document regulations

## Configuration Requirements

### Environment Variables
Standard Laravel and Filament configuration. No additional env vars needed.

### Claude AI (Optional)
If Claude AI is configured in CompanyProfile:
- API key and model must be set
- `isClaudeEnabled()` must return true
- Goods descriptions will use AI
- Falls back to basic generation if unavailable

### Nextcloud
- Project must have `nextcloud_folder_created = true`
- Project folder structure must exist
- Standard NextcloudService configuration

## Testing Checklist

- [x] Migration runs successfully
- [x] DDT tab appears in assembly log edit form
- [x] "Generate DDT" action visible when no DDT exists
- [x] DDT generates with correct numbering
- [x] AI description generation works (or fallback)
- [x] PDF uploads to correct Nextcloud path
- [x] View DDT opens PDF in browser
- [x] Download DDT retrieves file
- [x] Sign DDT adds signature info
- [x] Signed PDF uploaded separately
- [x] DDT status badge shows correctly in table

## Future Enhancements (Optional)

1. **Digital Signature Pad**
   - Integrate JavaScript signature pad
   - Store as base64 image
   - Embed in PDF

2. **Email Delivery**
   - Send DDT to customer email
   - Include PDF as attachment
   - Track delivery status

3. **Barcode/QR Code**
   - Add QR code to DDT for tracking
   - Link to project/assembly info

4. **Multi-Item DDTs**
   - Support multiple assembly logs in one DDT
   - Consolidated deliveries

5. **Transport Carrier Integration**
   - Select from carrier list
   - Auto-fill carrier details
   - Tracking number integration

6. **DDT Archive/Search**
   - Dedicated DDT listing page
   - Advanced search/filters
   - Export to Excel

## Support & Maintenance

### Logs
- Check `/storage/logs/laravel.log` for:
  - DDT generation errors
  - AI API failures
  - Nextcloud upload issues
  - PDF generation problems

### Common Issues

**DDT not generating**:
- Check project has customer assigned
- Verify Nextcloud folder created
- Check storage/temp/ddt folder permissions

**AI description failing**:
- Verify Claude API key in CompanyProfile
- Check API quota/limits
- Review logs for API errors
- System falls back to basic description automatically

**PDF not displaying**:
- Verify DomPDF is installed (`composer require barryvdh/laravel-dompdf`)
- Check DejaVu Sans font availability
- Review PDF blade syntax errors

**Nextcloud upload failing**:
- Verify project Nextcloud folder exists
- Check NextcloudService credentials
- Ensure folder permissions correct
- Verify folder structure (06_Consegna/DDT)

## Compliance Notes

This implementation follows Italian regulations for DDT:
- References DPR 472/96
- Includes all required fields
- Proper signature sections
- Transport type selection
- Causale del trasporto
- Package details

For legal compliance, consult with Italian accountant/commercialista.
