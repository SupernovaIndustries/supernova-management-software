<?php

namespace App\Services;

use App\Models\Component;
use App\Models\ComponentCertification;
use App\Models\Project;
use App\Models\ProjectBom;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class CertificationManagementService
{
    /**
     * Analyze project for CE marking compliance
     */
    public function analyzeCeCompliance(Project $project): array
    {
        $analysis = [
            'compliance_status' => 'unknown',
            'required_certifications' => $this->getCeRequiredCertifications(),
            'component_analysis' => [],
            'missing_certifications' => [],
            'expiring_certifications' => [],
            'recommendations' => [],
            'overall_score' => 0,
        ];

        // Get all components used in project BOMs
        $components = $this->getProjectComponents($project);
        
        foreach ($components as $component) {
            $componentAnalysis = $this->analyzeComponentCertifications($component);
            $analysis['component_analysis'][] = $componentAnalysis;
            
            // Collect missing certifications
            foreach ($componentAnalysis['missing_certifications'] as $missing) {
                if (!in_array($missing, $analysis['missing_certifications'])) {
                    $analysis['missing_certifications'][] = $missing;
                }
            }
            
            // Collect expiring certifications
            $analysis['expiring_certifications'] = array_merge(
                $analysis['expiring_certifications'],
                $componentAnalysis['expiring_certifications']
            );
        }

        // Calculate overall compliance score
        $analysis['overall_score'] = $this->calculateComplianceScore($analysis);
        
        // Determine compliance status
        $analysis['compliance_status'] = $this->determineComplianceStatus($analysis['overall_score']);
        
        // Generate recommendations
        $analysis['recommendations'] = $this->generateCeRecommendations($analysis);

        return $analysis;
    }

    /**
     * Get components used in project
     */
    private function getProjectComponents(Project $project): Collection
    {
        return Component::whereHas('bomItems.bom', function ($query) use ($project) {
            $query->where('project_id', $project->id);
        })->with(['certifications', 'lifecycleStatus'])->get();
    }

    /**
     * Analyze certifications for a single component
     */
    public function analyzeComponentCertifications(Component $component): array
    {
        $certifications = $component->certifications()->valid()->get();
        $requiredCerts = $this->getCeRequiredCertifications();
        
        $analysis = [
            'component_id' => $component->id,
            'component_name' => $component->name,
            'manufacturer' => $component->manufacturer,
            'valid_certifications' => [],
            'missing_certifications' => [],
            'expiring_certifications' => [],
            'compliance_score' => 0,
            'risk_level' => 'low',
        ];

        // Check each required certification
        foreach ($requiredCerts as $certType => $certName) {
            $cert = $certifications->firstWhere('certification_type', $certType);
            
            if ($cert) {
                $analysis['valid_certifications'][] = [
                    'type' => $certType,
                    'name' => $certName,
                    'certificate_number' => $cert->certificate_number,
                    'expiry_date' => $cert->expiry_date,
                    'status' => $cert->status,
                ];
                
                // Check if expiring soon
                if ($cert->isExpiringSoon()) {
                    $analysis['expiring_certifications'][] = [
                        'type' => $certType,
                        'name' => $certName,
                        'expiry_date' => $cert->expiry_date,
                        'days_until_expiry' => $cert->days_until_expiry,
                    ];
                }
            } else {
                $analysis['missing_certifications'][] = $certType;
            }
        }

        // Calculate component compliance score
        $totalRequired = count($requiredCerts);
        $validCount = count($analysis['valid_certifications']);
        $analysis['compliance_score'] = $totalRequired > 0 ? ($validCount / $totalRequired) * 100 : 0;

        // Determine risk level
        $analysis['risk_level'] = $this->calculateComponentRisk($analysis);

        return $analysis;
    }

    /**
     * Get CE marking required certifications
     */
    private function getCeRequiredCertifications(): array
    {
        return [
            'CE' => 'CE Marking',
            'EMC' => 'Electromagnetic Compatibility (EMC)',
            'LVD' => 'Low Voltage Directive (LVD)',
            'RoHS' => 'Restriction of Hazardous Substances (RoHS)',
            'REACH' => 'Registration, Evaluation, Authorisation of Chemicals (REACH)',
        ];
    }

    /**
     * Calculate overall project compliance score
     */
    private function calculateComplianceScore(array $analysis): float
    {
        if (empty($analysis['component_analysis'])) {
            return 0;
        }

        $totalScore = 0;
        foreach ($analysis['component_analysis'] as $componentAnalysis) {
            $totalScore += $componentAnalysis['compliance_score'];
        }

        return $totalScore / count($analysis['component_analysis']);
    }

    /**
     * Determine compliance status based on score
     */
    private function determineComplianceStatus(float $score): string
    {
        if ($score >= 95) return 'compliant';
        if ($score >= 80) return 'mostly_compliant';
        if ($score >= 60) return 'partially_compliant';
        return 'non_compliant';
    }

    /**
     * Calculate component risk level
     */
    private function calculateComponentRisk(array $analysis): string
    {
        $score = $analysis['compliance_score'];
        $expiringCount = count($analysis['expiring_certifications']);
        
        if ($score < 50 || $expiringCount > 2) return 'high';
        if ($score < 80 || $expiringCount > 0) return 'medium';
        return 'low';
    }

    /**
     * Generate CE compliance recommendations
     */
    private function generateCeRecommendations(array $analysis): array
    {
        $recommendations = [];

        // Missing certifications
        if (!empty($analysis['missing_certifications'])) {
            $recommendations[] = [
                'type' => 'missing_certifications',
                'priority' => 'high',
                'title' => 'Missing Required Certifications',
                'description' => 'Some components lack required certifications for CE marking',
                'action' => 'Contact suppliers to obtain missing certification documents',
                'certifications' => $analysis['missing_certifications'],
            ];
        }

        // Expiring certifications
        if (!empty($analysis['expiring_certifications'])) {
            $recommendations[] = [
                'type' => 'expiring_certifications',
                'priority' => 'medium',
                'title' => 'Certifications Expiring Soon',
                'description' => 'Some certifications will expire within 90 days',
                'action' => 'Request updated certification documents from suppliers',
                'certifications' => $analysis['expiring_certifications'],
            ];
        }

        // Low compliance score
        if ($analysis['overall_score'] < 80) {
            $recommendations[] = [
                'type' => 'low_compliance',
                'priority' => 'high',
                'title' => 'Low Compliance Score',
                'description' => "Project compliance score is {$analysis['overall_score']}%, below recommended 80%",
                'action' => 'Review component selection and obtain missing certifications',
            ];
        }

        // Component substitution suggestions
        $highRiskComponents = collect($analysis['component_analysis'])
            ->where('risk_level', 'high')
            ->count();

        if ($highRiskComponents > 0) {
            $recommendations[] = [
                'type' => 'component_substitution',
                'priority' => 'medium',
                'title' => 'Consider Component Substitutions',
                'description' => "{$highRiskComponents} components have high certification risks",
                'action' => 'Evaluate alternative components with better certification coverage',
            ];
        }

        return $recommendations;
    }

    /**
     * Generate certification report for project
     */
    public function generateCertificationReport(Project $project): array
    {
        $analysis = $this->analyzeCeCompliance($project);
        
        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'code' => $project->code,
                'customer' => $project->customer->company_name ?? $project->customer->name,
                'status' => $project->status,
            ],
            'report_date' => now(),
            'compliance_analysis' => $analysis,
            'certification_matrix' => $this->generateCertificationMatrix($project),
            'action_plan' => $this->generateActionPlan($analysis),
            'document_checklist' => $this->generateDocumentChecklist($analysis),
        ];
    }

    /**
     * Generate certification matrix showing which components have which certifications
     */
    private function generateCertificationMatrix(Project $project): array
    {
        $components = $this->getProjectComponents($project);
        $requiredCerts = $this->getCeRequiredCertifications();
        
        $matrix = [];
        foreach ($components as $component) {
            $row = [
                'component_id' => $component->id,
                'component_name' => $component->name,
                'manufacturer' => $component->manufacturer,
                'certifications' => [],
            ];

            foreach ($requiredCerts as $certType => $certName) {
                $cert = $component->certifications()
                    ->where('certification_type', $certType)
                    ->valid()
                    ->first();

                $row['certifications'][$certType] = [
                    'has_certification' => !is_null($cert),
                    'certificate_number' => $cert->certificate_number ?? null,
                    'expiry_date' => $cert->expiry_date ?? null,
                    'status' => $cert->status ?? 'missing',
                    'expiring_soon' => $cert ? $cert->isExpiringSoon() : false,
                ];
            }

            $matrix[] = $row;
        }

        return $matrix;
    }

    /**
     * Generate action plan based on analysis
     */
    private function generateActionPlan(array $analysis): array
    {
        $actions = [];
        $priority = 1;

        // High priority: Missing critical certifications
        $criticalMissing = array_intersect($analysis['missing_certifications'], ['CE', 'EMC', 'LVD']);
        if (!empty($criticalMissing)) {
            $actions[] = [
                'priority' => $priority++,
                'task' => 'Obtain Critical Certifications',
                'description' => 'Obtain missing critical certifications: ' . implode(', ', $criticalMissing),
                'due_date' => now()->addWeeks(2),
                'responsible' => 'Procurement Team',
                'status' => 'pending',
            ];
        }

        // Medium priority: Expiring certifications
        if (!empty($analysis['expiring_certifications'])) {
            $actions[] = [
                'priority' => $priority++,
                'task' => 'Renew Expiring Certifications',
                'description' => 'Contact suppliers for certification renewals',
                'due_date' => now()->addWeeks(4),
                'responsible' => 'Quality Team',
                'status' => 'pending',
            ];
        }

        // Lower priority: Other missing certifications
        $otherMissing = array_diff($analysis['missing_certifications'], ['CE', 'EMC', 'LVD']);
        if (!empty($otherMissing)) {
            $actions[] = [
                'priority' => $priority++,
                'task' => 'Complete Certification Set',
                'description' => 'Obtain remaining certifications: ' . implode(', ', $otherMissing),
                'due_date' => now()->addWeeks(6),
                'responsible' => 'Procurement Team',
                'status' => 'pending',
            ];
        }

        return $actions;
    }

    /**
     * Generate document checklist for CE marking
     */
    private function generateDocumentChecklist(array $analysis): array
    {
        return [
            'technical_documentation' => [
                'required' => true,
                'status' => 'pending',
                'description' => 'Complete technical documentation file',
            ],
            'declaration_of_conformity' => [
                'required' => true,
                'status' => 'pending',
                'description' => 'EU Declaration of Conformity',
            ],
            'component_certificates' => [
                'required' => true,
                'status' => $analysis['overall_score'] >= 95 ? 'complete' : 'pending',
                'description' => 'All component certification documents',
            ],
            'test_reports' => [
                'required' => true,
                'status' => 'pending',
                'description' => 'EMC and LVD test reports',
            ],
            'risk_assessment' => [
                'required' => true,
                'status' => 'pending',
                'description' => 'Risk assessment documentation',
            ],
            'user_manual' => [
                'required' => true,
                'status' => 'pending',
                'description' => 'User manual with safety instructions',
            ],
        ];
    }

    /**
     * Track certification expiries and send alerts
     */
    public function checkExpiringCertifications(int $daysAhead = 90): array
    {
        $expiring = ComponentCertification::expiringSoon($daysAhead)
            ->with('component')
            ->get();

        $alerts = [];
        foreach ($expiring as $cert) {
            $alerts[] = [
                'certification_id' => $cert->id,
                'component_name' => $cert->component->name,
                'certification_type' => $cert->certification_type,
                'expiry_date' => $cert->expiry_date,
                'days_until_expiry' => $cert->days_until_expiry,
                'urgency' => $this->calculateExpiryUrgency($cert->days_until_expiry),
            ];
        }

        return $alerts;
    }

    /**
     * Calculate urgency level based on days until expiry
     */
    private function calculateExpiryUrgency(int $days): string
    {
        if ($days <= 30) return 'critical';
        if ($days <= 60) return 'high';
        return 'medium';
    }

    /**
     * Import certification from file/API
     */
    public function importCertification(
        Component $component,
        string $certificationType,
        array $certificationData
    ): ComponentCertification {
        return ComponentCertification::create([
            'component_id' => $component->id,
            'certification_type' => $certificationType,
            'certificate_number' => $certificationData['certificate_number'] ?? null,
            'issuing_authority' => $certificationData['issuing_authority'] ?? null,
            'issue_date' => isset($certificationData['issue_date']) 
                ? Carbon::parse($certificationData['issue_date']) 
                : null,
            'expiry_date' => isset($certificationData['expiry_date']) 
                ? Carbon::parse($certificationData['expiry_date']) 
                : null,
            'status' => $certificationData['status'] ?? 'valid',
            'scope' => $certificationData['scope'] ?? null,
            'test_standards' => $certificationData['test_standards'] ?? null,
            'certificate_file_path' => $certificationData['certificate_file_path'] ?? null,
            'notes' => $certificationData['notes'] ?? null,
        ]);
    }

    /**
     * Get certification coverage statistics
     */
    public function getCertificationStatistics(): array
    {
        $totalComponents = Component::count();
        $requiredCerts = $this->getCeRequiredCertifications();
        
        $stats = [
            'total_components' => $totalComponents,
            'certification_coverage' => [],
            'expiring_soon' => ComponentCertification::expiringSoon()->count(),
            'expired' => ComponentCertification::where('status', 'expired')->count(),
        ];

        foreach ($requiredCerts as $certType => $certName) {
            $withCert = Component::whereHas('certifications', function ($query) use ($certType) {
                $query->where('certification_type', $certType)->where('status', 'valid');
            })->count();

            $stats['certification_coverage'][$certType] = [
                'name' => $certName,
                'components_with_cert' => $withCert,
                'coverage_percentage' => $totalComponents > 0 ? ($withCert / $totalComponents) * 100 : 0,
            ];
        }

        return $stats;
    }
}