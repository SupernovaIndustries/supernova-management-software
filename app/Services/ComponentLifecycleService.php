<?php

namespace App\Services;

use App\Models\Component;
use App\Models\ComponentLifecycleStatus;
use App\Models\ComponentAlternative;
use App\Models\ObsolescenceAlert;
use App\Models\Project;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class ComponentLifecycleService
{
    /**
     * Check all components for lifecycle issues and generate alerts
     */
    public function checkLifecycleStatus(): array
    {
        $results = [
            'alerts_created' => 0,
            'components_checked' => 0,
            'critical_issues' => 0,
        ];

        $lifecycleStatuses = ComponentLifecycleStatus::with('component')->get();
        
        foreach ($lifecycleStatuses as $status) {
            $results['components_checked']++;
            
            $alerts = $this->generateAlertsForComponent($status);
            $results['alerts_created'] += count($alerts);
            
            if ($status->urgency_level === 'critical') {
                $results['critical_issues']++;
            }
        }

        return $results;
    }

    /**
     * Generate alerts for a specific component
     */
    public function generateAlertsForComponent(ComponentLifecycleStatus $status): array
    {
        $alerts = [];
        $component = $status->component;

        // EOL Warning (6+ months before EOL)
        if ($status->lifecycle_stage === 'eol_announced' && 
            $status->eol_date && 
            $status->eol_date->diffInMonths() >= 6) {
            
            $alerts[] = $this->createAlert($component, 'eol_warning', 'medium', 
                'EOL Warning: ' . $component->name,
                "Component {$component->name} will reach End of Life on {$status->eol_date->format('Y-m-d')}. Consider finding alternatives."
            );
        }

        // EOL Imminent (< 6 months before EOL)
        if ($status->lifecycle_stage === 'eol_announced' && 
            $status->eol_date && 
            $status->eol_date->diffInMonths() < 6) {
            
            $alerts[] = $this->createAlert($component, 'eol_imminent', 'high',
                'EOL Imminent: ' . $component->name,
                "Component {$component->name} will reach End of Life in {$status->days_until_eol} days. Immediate action required."
            );
        }

        // Last Time Buy
        if ($status->last_time_buy_date && 
            $status->last_time_buy_date->diffInDays() <= 30) {
            
            $alerts[] = $this->createAlert($component, 'last_time_buy', 'critical',
                'Last Time Buy: ' . $component->name,
                "Last chance to order {$component->name}. Last time buy date: {$status->last_time_buy_date->format('Y-m-d')}"
            );
        }

        // Obsolete
        if ($status->lifecycle_stage === 'obsolete') {
            $alerts[] = $this->createAlert($component, 'obsolete', 'critical',
                'Component Obsolete: ' . $component->name,
                "Component {$component->name} is now obsolete. Find alternatives immediately."
            );
        }

        return $alerts;
    }

    /**
     * Create an alert for a component
     */
    private function createAlert(Component $component, string $type, string $severity, string $title, string $message): ObsolescenceAlert
    {
        // Check if alert already exists
        $existingAlert = ObsolescenceAlert::where('component_id', $component->id)
            ->where('alert_type', $type)
            ->where('is_resolved', false)
            ->first();

        if ($existingAlert) {
            return $existingAlert;
        }

        // Find affected projects
        $affectedProjects = $this->findAffectedProjects($component);

        return ObsolescenceAlert::create([
            'component_id' => $component->id,
            'alert_type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'affected_projects' => $affectedProjects->pluck('id')->toArray(),
            'alert_date' => now(),
        ]);
    }

    /**
     * Find projects that use this component
     */
    public function findAffectedProjects(Component $component): Collection
    {
        // This would need to be implemented based on your BOM structure
        // For now, return projects that might be affected
        return Project::where('project_status', 'active')
            ->whereHas('boms.items', function ($query) use ($component) {
                $query->where('component_id', $component->id);
            })
            ->get();
    }

    /**
     * Suggest alternatives for a component
     */
    public function suggestAlternatives(Component $component, array $criteria = []): Collection
    {
        $query = ComponentAlternative::where('original_component_id', $component->id)
            ->with('alternativeComponent');

        // Filter by compatibility score
        if (isset($criteria['min_compatibility'])) {
            $query->where('compatibility_score', '>=', $criteria['min_compatibility']);
        }

        // Filter by alternative type
        if (isset($criteria['type'])) {
            $query->where('alternative_type', $criteria['type']);
        }

        // Only recommended alternatives
        if (isset($criteria['recommended_only']) && $criteria['recommended_only']) {
            $query->where('is_recommended', true);
        }

        return $query->orderByDesc('compatibility_score')
            ->orderByDesc('is_recommended')
            ->get();
    }

    /**
     * Add alternative component
     */
    public function addAlternative(
        Component $original, 
        Component $alternative, 
        string $type, 
        float $compatibilityScore,
        ?string $notes = null,
        array $differences = [],
        bool $isRecommended = false
    ): ComponentAlternative {
        return ComponentAlternative::create([
            'original_component_id' => $original->id,
            'alternative_component_id' => $alternative->id,
            'alternative_type' => $type,
            'compatibility_score' => $compatibilityScore,
            'compatibility_notes' => $notes,
            'differences' => $differences,
            'is_recommended' => $isRecommended,
        ]);
    }

    /**
     * Calculate automatic compatibility score based on component properties
     */
    public function calculateCompatibilityScore(Component $original, Component $alternative): float
    {
        $score = 0.0;
        $factors = 0;

        // Package compatibility (high weight)
        if ($original->package === $alternative->package) {
            $score += 0.3;
        }
        $factors++;

        // Category compatibility
        if ($original->category_id === $alternative->category_id) {
            $score += 0.2;
        }
        $factors++;

        // Manufacturer compatibility (same manufacturer = higher compatibility)
        if ($original->manufacturer === $alternative->manufacturer) {
            $score += 0.1;
        }
        $factors++;

        // Specifications similarity (would need more complex logic)
        $specSimilarity = $this->compareSpecifications($original, $alternative);
        $score += $specSimilarity * 0.4;
        $factors++;

        return min(1.0, $score);
    }

    /**
     * Compare specifications between components
     */
    private function compareSpecifications(Component $original, Component $alternative): float
    {
        // This would need to be implemented based on your specifications structure
        // For now, return a basic similarity score
        
        if (!$original->specifications || !$alternative->specifications) {
            return 0.5; // Neutral if no specs available
        }

        // Basic comparison - you'd want to implement proper spec comparison logic
        $originalSpecs = is_array($original->specifications) ? $original->specifications : [];
        $alternativeSpecs = is_array($alternative->specifications) ? $alternative->specifications : [];

        $commonKeys = array_intersect(array_keys($originalSpecs), array_keys($alternativeSpecs));
        $totalKeys = array_unique(array_merge(array_keys($originalSpecs), array_keys($alternativeSpecs)));

        if (empty($totalKeys)) {
            return 0.5;
        }

        $matches = 0;
        foreach ($commonKeys as $key) {
            if ($originalSpecs[$key] === $alternativeSpecs[$key]) {
                $matches++;
            }
        }

        return count($commonKeys) > 0 ? $matches / count($commonKeys) : 0.0;
    }

    /**
     * Get lifecycle summary for dashboard
     */
    public function getLifecycleSummary(): array
    {
        $total = ComponentLifecycleStatus::count();
        
        return [
            'total_components' => $total,
            'active' => ComponentLifecycleStatus::where('lifecycle_stage', 'active')->count(),
            'nrnd' => ComponentLifecycleStatus::where('lifecycle_stage', 'nrnd')->count(),
            'eol_announced' => ComponentLifecycleStatus::where('lifecycle_stage', 'eol_announced')->count(),
            'eol' => ComponentLifecycleStatus::where('lifecycle_stage', 'eol')->count(),
            'obsolete' => ComponentLifecycleStatus::where('lifecycle_stage', 'obsolete')->count(),
            'critical_alerts' => ObsolescenceAlert::unresolved()->critical()->count(),
            'pending_alerts' => ObsolescenceAlert::unresolved()->unacknowledged()->count(),
        ];
    }
}