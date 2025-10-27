<?php

namespace App\Services;

use App\Models\Project;
use App\Services\AiServiceFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * AI-powered project management service for priority calculation and optimization.
 */
class ProjectManagementAiService
{
    /**
     * Calculate AI priority scores for all active projects.
     */
    public function calculateProjectPriorities(): array
    {
        Log::info('Starting AI project priority calculation');

        // Get all active projects with relations
        $projects = Project::active()
            ->with(['customer', 'milestones', 'priority'])
            ->get();

        if ($projects->isEmpty()) {
            Log::info('No active projects found');
            return [
                'success' => true,
                'message' => 'Nessun progetto attivo da analizzare',
                'projects_analyzed' => 0,
            ];
        }

        $analyzed = 0;
        $errors = 0;
        $conflicts = [];

        foreach ($projects as $project) {
            try {
                $priorityData = $this->calculateProjectPriority($project, $projects);

                $project->update([
                    'ai_priority_score' => $priorityData['score'],
                    'ai_priority_data' => $priorityData,
                    'ai_priority_calculated_at' => now(),
                ]);

                // Detect conflicts
                if ($priorityData['score'] >= 80) {
                    $conflicts[] = [
                        'project' => $project->code . ' - ' . $project->name,
                        'score' => $priorityData['score'],
                        'reason' => $priorityData['reason'],
                    ];
                }

                $analyzed++;
            } catch (\Exception $e) {
                $errors++;
                Log::error('Failed to calculate priority for project', [
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Use AI to analyze conflicts and suggest optimizations
        $aiSuggestions = null;
        if (count($conflicts) > 0) {
            $aiSuggestions = $this->analyzeConflictsWithAi($conflicts, $projects);
        }

        Log::info('AI project priority calculation completed', [
            'analyzed' => $analyzed,
            'errors' => $errors,
            'conflicts' => count($conflicts),
        ]);

        return [
            'success' => true,
            'projects_analyzed' => $analyzed,
            'errors' => $errors,
            'conflicts' => $conflicts,
            'ai_suggestions' => $aiSuggestions,
        ];
    }

    /**
     * Calculate priority score for a single project.
     */
    private function calculateProjectPriority(Project $project, Collection $allProjects): array
    {
        $score = 0;
        $factors = [];

        // Factor 1: Days until deadline (weight: 40%)
        $daysUntilDeadline = $project->getDaysUntilDeadline();
        if ($daysUntilDeadline !== null) {
            if ($daysUntilDeadline < 0) {
                // Overdue
                $deadlineFactor = 100;
                $factors['deadline'] = "Progetto in ritardo di " . abs($daysUntilDeadline) . " giorni";
            } elseif ($daysUntilDeadline <= 7) {
                $deadlineFactor = 90;
                $factors['deadline'] = "Scadenza imminente: {$daysUntilDeadline} giorni";
            } elseif ($daysUntilDeadline <= 14) {
                $deadlineFactor = 70;
                $factors['deadline'] = "Scadenza vicina: {$daysUntilDeadline} giorni";
            } elseif ($daysUntilDeadline <= 30) {
                $deadlineFactor = 50;
                $factors['deadline'] = "Scadenza a {$daysUntilDeadline} giorni";
            } else {
                $deadlineFactor = 30;
                $factors['deadline'] = "Scadenza tra {$daysUntilDeadline} giorni";
            }
            $score += $deadlineFactor * 0.4;
        }

        // Factor 2: Milestones remaining (weight: 30%)
        $totalMilestones = $project->milestones()->count();
        $completedMilestones = $project->milestones()->wherePivot('is_completed', true)->count();
        $remainingMilestones = $totalMilestones - $completedMilestones;

        if ($totalMilestones > 0) {
            $completionPercentage = ($completedMilestones / $totalMilestones) * 100;

            if ($remainingMilestones > 5) {
                $milestoneFactor = 80;
                $factors['milestones'] = "Molte milestone da completare ({$remainingMilestones})";
            } elseif ($remainingMilestones > 3) {
                $milestoneFactor = 60;
                $factors['milestones'] = "{$remainingMilestones} milestone rimanenti";
            } elseif ($remainingMilestones > 0) {
                $milestoneFactor = 40;
                $factors['milestones'] = "{$remainingMilestones} milestone rimanenti";
            } else {
                $milestoneFactor = 20;
                $factors['milestones'] = "Tutte milestone completate";
            }
            $score += $milestoneFactor * 0.3;
        }

        // Factor 3: Time overlap with other projects (weight: 20%)
        $overlapFactor = $this->calculateTimeOverlap($project, $allProjects);
        $score += $overlapFactor * 0.2;
        $factors['overlap'] = "Sovrapposizione temporale: " . round($overlapFactor) . "%";

        // Factor 4: Budget/Customer importance (weight: 10%)
        $budgetFactor = 50; // default
        if ($project->budget) {
            if ($project->budget > 50000) {
                $budgetFactor = 80;
                $factors['budget'] = "Budget alto (€" . number_format($project->budget, 0) . ")";
            } elseif ($project->budget > 20000) {
                $budgetFactor = 60;
                $factors['budget'] = "Budget medio-alto (€" . number_format($project->budget, 0) . ")";
            } else {
                $budgetFactor = 40;
                $factors['budget'] = "Budget standard (€" . number_format($project->budget, 0) . ")";
            }
        }
        $score += $budgetFactor * 0.1;

        // Round final score
        $score = min(100, round($score));

        // Generate reason
        $reason = $this->generatePriorityReason($score, $factors);

        return [
            'score' => (int) $score,
            'factors' => $factors,
            'reason' => $reason,
            'calculated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Calculate time overlap with other projects.
     */
    private function calculateTimeOverlap(Project $project, Collection $allProjects): float
    {
        if (!$project->start_date || !$project->due_date) {
            return 0;
        }

        $projectStart = Carbon::parse($project->start_date);
        $projectEnd = Carbon::parse($project->due_date);
        $overlapCount = 0;

        foreach ($allProjects as $otherProject) {
            if ($otherProject->id === $project->id) {
                continue;
            }

            if (!$otherProject->start_date || !$otherProject->due_date) {
                continue;
            }

            $otherStart = Carbon::parse($otherProject->start_date);
            $otherEnd = Carbon::parse($otherProject->due_date);

            // Check for overlap
            if ($projectStart <= $otherEnd && $projectEnd >= $otherStart) {
                $overlapCount++;
            }
        }

        // Convert to factor (0-100)
        if ($overlapCount >= 5) return 100;
        if ($overlapCount >= 3) return 70;
        if ($overlapCount >= 2) return 50;
        if ($overlapCount >= 1) return 30;
        return 0;
    }

    /**
     * Generate human-readable priority reason.
     */
    private function generatePriorityReason(int $score, array $factors): string
    {
        if ($score >= 90) {
            return "URGENTE: " . ($factors['deadline'] ?? 'Richiede attenzione immediata');
        } elseif ($score >= 70) {
            return "Alta priorità: " . ($factors['deadline'] ?? $factors['milestones'] ?? 'Progetto critico');
        } elseif ($score >= 50) {
            return "Priorità media: " . ($factors['milestones'] ?? 'Progetto in corso');
        } else {
            return "Priorità standard: Progetto sotto controllo";
        }
    }

    /**
     * Use AI to analyze conflicts and suggest optimizations.
     */
    private function analyzeConflictsWithAi(array $conflicts, Collection $projects): ?string
    {
        try {
            $aiService = AiServiceFactory::make();

            if (!$aiService->isConfigured()) {
                return null;
            }

            // Create a fake project notification to leverage existing AI method
            // This is a workaround to use the interface method
            $prompt = $this->buildConflictAnalysisPrompt($conflicts, $projects);

            // Try to use reflection to call makeRequest if available (Ollama specific)
            if (method_exists($aiService, 'makeRequest')) {
                $reflection = new \ReflectionClass($aiService);
                $method = $reflection->getMethod('makeRequest');
                $method->setAccessible(true);
                $response = $method->invoke($aiService, $prompt, 1000);
                return $response;
            }

            // Fallback: Return basic analysis if AI method not available
            return $this->generateBasicAnalysis($conflicts);

        } catch (\Exception $e) {
            Log::error('Failed to analyze conflicts with AI', [
                'error' => $e->getMessage(),
            ]);
            return $this->generateBasicAnalysis($conflicts);
        }
    }

    /**
     * Generate basic conflict analysis without AI.
     */
    private function generateBasicAnalysis(array $conflicts): string
    {
        $highPriorityCount = count(array_filter($conflicts, fn($c) => $c['score'] >= 90));
        $mediumPriorityCount = count(array_filter($conflicts, fn($c) => $c['score'] >= 70 && $c['score'] < 90));

        $analysis = "ANALISI PRIORITA' PROGETTI:\n\n";

        if ($highPriorityCount > 0) {
            $analysis .= "🚨 {$highPriorityCount} progetti ad URGENZA MASSIMA richiedono attenzione immediata.\n";
        }

        if ($mediumPriorityCount > 0) {
            $analysis .= "⚠️ {$mediumPriorityCount} progetti ad ALTA PRIORITA' da monitorare attentamente.\n";
        }

        $analysis .= "\nRACCOMANDAZIONI:\n";
        $analysis .= "• Verificare disponibilità risorse per progetti urgenti\n";
        $analysis .= "• Considerare slittamento date per progetti con priorità più bassa\n";
        $analysis .= "• Monitorare daily le milestone in scadenza nei prossimi 7 giorni\n";

        return $analysis;
    }

    /**
     * Build prompt for AI conflict analysis.
     */
    private function buildConflictAnalysisPrompt(array $conflicts, Collection $projects): string
    {
        $conflictList = '';
        foreach ($conflicts as $conflict) {
            $conflictList .= "- {$conflict['project']} (Score: {$conflict['score']}/100) - {$conflict['reason']}\n";
        }

        $totalProjects = $projects->count();

        return "Sei un esperto di project management per Supernova Industries S.R.L.

Analizza questi progetti elettronici con alta priorità e possibili conflitti di risorse:

PROGETTI AD ALTA PRIORITÀ ({$totalProjects} progetti attivi totali):
{$conflictList}

OBIETTIVO:
1. Identifica i principali rischi di sovrapposizione
2. Suggerisci ottimizzazioni delle date/milestone
3. Raccomanda azioni per ridurre conflitti
4. Priorità di intervento

Rispondi in italiano con suggerimenti pratici e concreti (max 200 parole).";
    }
}
