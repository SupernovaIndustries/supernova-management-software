# Assembly Checklist AI-Powered Generation

## Overview

The Assembly Checklist system automatically generates comprehensive, AI-powered checklists for PCB assembly operations. Each time a new `BoardAssemblyLog` is created, a customized checklist is automatically generated based on project characteristics, assembly type, and BOM complexity.

## Features

- **AI-Powered Generation**: Uses Claude AI to generate context-aware, professional checklists
- **Automatic Triggering**: Checklists are auto-generated when assembly logs are created
- **Template Fallback**: Falls back to template-based or basic checklists if AI is unavailable
- **IPC-A-610 Compliant**: Follows industry standards for PCB assembly quality control
- **Categorized Items**: Items organized into 6 key categories for logical workflow
- **Progress Tracking**: Real-time tracking of completion percentage and item status
- **Critical Items**: Safety and quality-critical items flagged for special attention

## Architecture

### Models

#### AssemblyChecklist
- Links to `BoardAssemblyLog` (one-to-many relationship)
- Tracks completion status, progress, and approval
- Fields: `status`, `completion_percentage`, `total_items`, `completed_items`, etc.

#### AssemblyChecklistTemplate
- Stores reusable checklist templates
- Can be generated dynamically by AI or created manually
- Fields: `board_type`, `complexity_level`, `is_active`, `metadata`

#### AssemblyChecklistItem
- Individual checklist items within a template
- Fields: `title`, `description`, `instructions`, `type`, `category`, `is_critical`
- Types: checkbox, text, number, measurement, photo, file, signature, multiselect

#### AssemblyChecklistResponse
- Tracks completion status for each item in a checklist instance
- Fields: `status`, `response_data`, `notes`, `completed_at`

### Services

#### AssemblyChecklistService
**Location**: `/app/Services/AssemblyChecklistService.php`

**Key Methods**:
- `generateChecklistForAssembly(BoardAssemblyLog $log)`: Main entry point for checklist generation
- `generateWithAI(BoardAssemblyLog $log)`: AI-powered generation using Claude
- `generateFromTemplate(BoardAssemblyLog $log)`: Template-based generation
- `createBasicChecklist(BoardAssemblyLog $log)`: Fallback basic checklist
- `regenerateChecklist(AssemblyChecklist $checklist)`: Delete and regenerate checklist

**AI Prompt Structure**:
The service builds a comprehensive prompt including:
- Project name and code
- Number of boards
- Assembly type (test/prototype vs production)
- Complexity assessment (based on BOM)
- Component information from BOM
- IPC-A-610 standards reference

**Response Parsing**:
- Expects JSON array of checklist items
- Validates and normalizes all fields
- Handles AI errors gracefully with fallbacks

### Observer

#### BoardAssemblyLogObserver
**Location**: `/app/Observers/BoardAssemblyLogObserver.php`

**Auto-generation on `created` event**:
```php
public function created(BoardAssemblyLog $boardAssemblyLog): void
{
    // ... QR code generation ...

    // Auto-generate assembly checklist
    $checklist = $this->checklistService->generateChecklistForAssembly($boardAssemblyLog);
}
```

## Checklist Categories

The AI generates items across 6 standardized categories:

1. **Pre-Assembly**
   - ESD protection setup
   - Material verification
   - Tool preparation
   - Workspace setup

2. **Component Inspection**
   - Part number verification
   - Component quality checks
   - Package inspection
   - Moisture sensitivity handling

3. **Soldering**
   - SMD reflow operations
   - Through-hole soldering
   - Touch-up and rework
   - Solder joint inspection

4. **Testing**
   - Continuity testing
   - Power supply verification
   - Functional testing
   - Signal integrity checks

5. **QC (Quality Control)**
   - Visual inspection per IPC-A-610
   - X-ray inspection (if applicable)
   - AOI results verification
   - Photo documentation

6. **Packaging**
   - Labeling and serialization
   - ESD-safe packaging
   - Documentation inclusion
   - Final shipment preparation

## Filament Integration

### BoardAssemblyLogsRelationManager

**Location**: `/app/Filament/Resources/ProjectResource/RelationManagers/BoardAssemblyLogsRelationManager.php`

**Table Column**:
- `assemblyChecklist_count`: Shows completion percentage badge
- Color: Green if exists, gray if not
- Tooltip: Displays status and item counts

**Actions**:

1. **viewChecklist**
   - Icon: clipboard-document-check
   - Visible when checklist exists
   - Opens slide-over modal with full checklist view
   - Shows progress, status, category breakdown, all items

2. **generateChecklist**
   - Icon: sparkles
   - Visible when NO checklist exists
   - Manually triggers checklist generation
   - Useful if auto-generation failed

3. **regenerateChecklist**
   - Icon: arrow-path
   - Visible when checklist exists
   - Deletes existing and creates new checklist
   - Requires confirmation

### Modal View

**Location**: `/resources/views/filament/modals/assembly-checklist.blade.php`

**Features**:
- Assembly log header with project info
- Progress overview cards (completion %, status, assignment)
- Items grouped by category
- Visual status indicators (green checkmark, red X, gray circle)
- Critical item badges
- Safety notes highlighted in yellow
- Instructions and response data displayed
- Print-friendly styling

## Database Schema

### Migration: `add_board_assembly_log_id_to_assembly_checklists_table`

```sql
ALTER TABLE assembly_checklists
ADD COLUMN board_assembly_log_id BIGINT NULL,
ADD CONSTRAINT assembly_checklists_board_assembly_log_id_foreign
    FOREIGN KEY (board_assembly_log_id)
    REFERENCES board_assembly_logs(id)
    ON DELETE CASCADE;

CREATE INDEX assembly_checklists_board_assembly_log_id_index
    ON assembly_checklists(board_assembly_log_id);
```

## AI Generation Details

### Prompt Engineering

The AI prompt is carefully crafted to generate professional, actionable checklists:

**Context Provided**:
- Project name and code
- Board count
- Assembly type (test/prototype vs production)
- Complexity level (simple/medium/complex/expert)
- BOM component count and package types

**Instructions to AI**:
- Generate 15-25 specific, actionable items
- Use technical PCB assembly terminology
- Follow IPC standards
- Categorize into 6 fixed categories
- Mark critical items for safety/quality
- Provide realistic time estimates
- Include detailed instructions where needed

**Output Format**:
```json
[
    {
        "title": "Verifica ESD Protection",
        "description": "Verificare che workstation sia dotata di ESD mat e wrist strap connessi",
        "instructions": "Testare continuità ESD mat e wrist strap con ohm meter (1-10 MOhm)",
        "type": "checkbox",
        "category": "Pre-Assembly",
        "is_required": true,
        "is_critical": true,
        "safety_notes": "ESD protection obbligatoria per componenti sensibili",
        "estimated_minutes": 2,
        "sort_order": 1
    }
]
```

### Fallback Chain

The service implements a robust fallback chain:

1. **AI Generation** (Primary)
   - Uses Claude AI with custom prompt
   - Generates dynamic, context-aware checklist
   - Creates new template with AI-generated items

2. **Template-Based** (Secondary)
   - Finds appropriate template by board_type
   - Uses default template if available
   - Creates checklist from template items

3. **Basic Checklist** (Tertiary)
   - Creates basic 12-item checklist
   - Covers essential assembly steps
   - Guaranteed to always work

### Error Handling

- All AI errors are logged but don't block assembly log creation
- Service methods return `null` on failure, triggering fallback
- Checklist can always be generated manually via UI action
- JSON parsing errors are caught and logged

## Usage Examples

### Automatic Generation

```php
// Create assembly log - checklist auto-generated via Observer
$log = BoardAssemblyLog::create([
    'project_id' => $project->id,
    'user_id' => auth()->id(),
    'assembly_date' => now(),
    'boards_count' => 10,
    'batch_number' => '001-PROJ-PROD',
    'is_prototype' => false,
    'status' => 'assembled',
]);

// Checklist is automatically created and available
$checklist = $log->assemblyChecklist->first();
echo "Checklist with {$checklist->total_items} items created!";
```

### Manual Generation

```php
use App\Services\AssemblyChecklistService;

$service = new AssemblyChecklistService();
$checklist = $service->generateChecklistForAssembly($log);

if ($checklist) {
    echo "Generated checklist: {$checklist->id}";
}
```

### Regeneration

```php
$service = new AssemblyChecklistService();
$newChecklist = $service->regenerateChecklist($existingChecklist);
```

## Configuration

### Enable Claude AI

1. Go to **Company Profile** in Filament admin
2. Enter **Claude API Key** in AI Configuration section
3. Select **Claude Model** (default: claude-3-5-sonnet-20241022)
4. Enable **Claude Enabled** toggle
5. Save configuration

### Create Custom Templates

Templates can be created manually via Filament admin:

1. Navigate to **Assembly Checklist Templates**
2. Create new template
3. Set board type, complexity, and requirements
4. Add checklist items with categories
5. Mark as default if desired

## Best Practices

### For Operators

1. **Review Before Starting**: Read through entire checklist before beginning
2. **Follow Order**: Complete items in category order for logical workflow
3. **Critical Items**: Pay special attention to items marked CRITICAL
4. **Document Issues**: Add notes for any deviations or problems
5. **Complete Fully**: Ensure all required items are completed

### For Supervisors

1. **Review Checklists**: Check completed checklists for quality
2. **Approve Critical Assemblies**: Approve expert-level assemblies
3. **Monitor Trends**: Track common failures or issues
4. **Update Templates**: Improve templates based on field feedback

### For Administrators

1. **Monitor AI Generation**: Check logs for AI generation success rate
2. **Maintain Templates**: Keep fallback templates up-to-date
3. **Review Failed Items**: Analyze commonly failed items
4. **Train Operators**: Use checklists for operator training

## Troubleshooting

### Checklist Not Generated

**Symptoms**: No checklist appears after creating assembly log

**Solutions**:
1. Check logs: `storage/logs/laravel.log` for errors
2. Verify Claude AI is configured and enabled
3. Manually trigger generation via UI action
4. Check Observer is registered in AppServiceProvider

### AI Generation Failing

**Symptoms**: Checklist created but using basic template instead of AI

**Solutions**:
1. Verify Claude API key is correct
2. Check API rate limits
3. Review logs for API errors
4. Test connection in Company Profile
5. Ensure internet connectivity

### Empty or Invalid Checklist

**Symptoms**: Checklist created but has 0 items or malformed items

**Solutions**:
1. Check template has items
2. Regenerate checklist
3. Verify database migrations ran successfully
4. Check for JSON parsing errors in logs

## Logging

The system logs all operations:

```php
// Success
Log::info('Assembly checklist auto-generated after assembly log creation', [
    'assembly_log_id' => $boardAssemblyLog->id,
    'checklist_id' => $checklist->id,
    'total_items' => $checklist->total_items,
]);

// Failure
Log::error('Failed to auto-generate assembly checklist', [
    'assembly_log_id' => $boardAssemblyLog->id,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
]);
```

Monitor logs for generation failures and AI issues.

## Future Enhancements

Potential improvements:

1. **Mobile App**: Native mobile app for checklist completion on factory floor
2. **Photo Upload**: Direct photo capture for visual inspection items
3. **Digital Signatures**: Capture operator and supervisor signatures
4. **Real-time Collaboration**: Multiple operators working on same checklist
5. **Analytics Dashboard**: Visualize completion rates, common failures
6. **Template Library**: Share templates across projects or organizations
7. **Automated Testing Integration**: Link test equipment results directly
8. **Barcode Scanning**: Scan components to verify correct parts used
9. **Time Tracking**: Automatic time tracking per item and category
10. **Notifications**: Alert supervisors when critical items fail

## Related Files

### Core Files
- `/app/Services/AssemblyChecklistService.php`
- `/app/Observers/BoardAssemblyLogObserver.php`
- `/app/Models/AssemblyChecklist.php`
- `/app/Models/AssemblyChecklistTemplate.php`
- `/app/Models/AssemblyChecklistItem.php`
- `/app/Models/AssemblyChecklistResponse.php`
- `/app/Models/BoardAssemblyLog.php`

### Filament Integration
- `/app/Filament/Resources/ProjectResource/RelationManagers/BoardAssemblyLogsRelationManager.php`
- `/resources/views/filament/modals/assembly-checklist.blade.php`

### Database
- `/database/migrations/2025_07_25_222833_create_assembly_checklist_templates_table.php`
- `/database/migrations/2025_07_25_999008_create_assembly_checklist_items_table.php`
- `/database/migrations/2025_07_25_999009_create_assembly_checklists_table.php`
- `/database/migrations/2025_07_25_999010_create_assembly_checklist_responses_table.php`
- `/database/migrations/2025_10_06_180949_add_board_assembly_log_id_to_assembly_checklists_table.php`

## Support

For issues or questions:
1. Check logs: `storage/logs/laravel.log`
2. Review this documentation
3. Contact development team
4. Create issue in project repository

---

**Last Updated**: 2025-10-06
**Version**: 1.0.0
**Author**: Supernova Management System
