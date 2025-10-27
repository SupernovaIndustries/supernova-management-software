<?php

namespace App\Console\Commands;

use App\Models\ProjectBom;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateBomCosts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bom:update-costs {--project-id= : Update costs for specific project} {--force : Force update even if costs are up to date}';

    /**
     * The console command description.
     */
    protected $description = 'Update BOM costs from inventory component prices';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Starting BOM cost update...');

        $query = ProjectBom::with(['items.component', 'project']);

        // Filter by project if specified
        if ($projectId = $this->option('project-id')) {
            $query->where('project_id', $projectId);
            $this->info("📌 Filtering by project ID: {$projectId}");
        }

        $boms = $query->get();

        if ($boms->isEmpty()) {
            $this->warn('⚠️  No BOMs found to update.');
            return self::SUCCESS;
        }

        $this->info("📊 Found {$boms->count()} BOMs to process");

        $progressBar = $this->output->createProgressBar($boms->count());
        $progressBar->start();

        $updated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($boms as $bom) {
            try {
                $itemsUpdated = 0;
                $force = $this->option('force');

                foreach ($bom->items as $item) {
                    if (!$item->component_id) {
                        continue; // Skip unallocated items
                    }

                    // Check if update is needed
                    if (!$force && $item->areCostsUpToDate()) {
                        continue;
                    }

                    $oldCost = $item->actual_unit_cost;
                    $item->updateActualCosts();
                    
                    if ($oldCost != $item->fresh()->actual_unit_cost) {
                        $itemsUpdated++;
                    }
                }

                if ($itemsUpdated > 0 || $force) {
                    $bom->calculateTotalCosts();
                    $updated++;
                    
                    Log::info("BOM costs updated", [
                        'bom_id' => $bom->id,
                        'project' => $bom->project->name,
                        'items_updated' => $itemsUpdated,
                        'total_actual_cost' => $bom->fresh()->total_actual_cost
                    ]);
                } else {
                    $skipped++;
                }

            } catch (\Exception $e) {
                $errors++;
                $this->error("❌ Error updating BOM {$bom->id}: " . $e->getMessage());
                
                Log::error("BOM cost update failed", [
                    'bom_id' => $bom->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info("✅ BOM cost update completed!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['BOMs Updated', $updated],
                ['BOMs Skipped (up to date)', $skipped],
                ['Errors', $errors],
                ['Total Processed', $boms->count()],
            ]
        );

        if ($updated > 0) {
            $this->info("💰 Updated costs for {$updated} BOMs from inventory component prices");
        }

        if ($errors > 0) {
            $this->warn("⚠️  {$errors} errors occurred. Check logs for details.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}