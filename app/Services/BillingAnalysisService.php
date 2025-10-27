<?php

namespace App\Services;

use App\Models\BillingAnalysis;
use App\Models\InvoiceIssued;
use App\Models\InvoiceReceived;
use App\Models\PaymentMilestone;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BillingAnalysisService
{
    /**
     * Generate monthly billing analysis for a specific month/year.
     */
    public function generateMonthlyAnalysis(int $year, int $month): BillingAnalysis
    {
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = Carbon::create($year, $month, 1)->endOfMonth();

        // Calculate revenue (only invoices that are sent, not draft)
        $revenueData = $this->calculateRevenue($periodStart, $periodEnd);

        // Calculate costs (all received invoices in the period)
        $costsData = $this->calculateCosts($periodStart, $periodEnd);

        // Calculate forecasts
        $forecastData = $this->calculateForecasts($periodStart, $periodEnd);

        // Calculate profit
        $grossProfit = $revenueData['total_invoiced'] - $costsData['total_costs'];
        $netProfit = $revenueData['total_paid'] - $costsData['total_costs'];
        $profitMargin = $revenueData['total_invoiced'] > 0
            ? ($grossProfit / $revenueData['total_invoiced']) * 100
            : 0;

        // Create or update analysis
        $analysis = BillingAnalysis::updateOrCreate(
            [
                'analysis_type' => 'monthly',
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ],
            [
                'total_revenue' => $revenueData['total_invoiced'],
                'total_invoiced' => $revenueData['total_invoiced'],
                'total_paid' => $revenueData['total_paid'],
                'total_outstanding' => $revenueData['total_outstanding'],
                'total_costs' => $costsData['total_costs'],
                'warehouse_costs' => $costsData['warehouse_costs'],
                'equipment_costs' => $costsData['equipment_costs'],
                'service_costs' => $costsData['service_costs'],
                'customs_costs' => $costsData['customs_costs'],
                'gross_profit' => $grossProfit,
                'net_profit' => $netProfit,
                'profit_margin' => round($profitMargin, 2),
                'forecasted_revenue' => $forecastData['revenue'],
                'forecasted_costs' => $forecastData['costs'],
                'details' => [
                    'revenue_breakdown' => $revenueData['breakdown'],
                    'costs_breakdown' => $costsData['breakdown'],
                    'forecast_details' => $forecastData['details'],
                ],
                'generated_at' => now(),
                'generated_by' => auth()->id(),
            ]
        );

        return $analysis;
    }

    /**
     * Calculate revenue data for a period.
     */
    protected function calculateRevenue(Carbon $start, Carbon $end): array
    {
        $invoices = InvoiceIssued::whereBetween('issue_date', [$start, $end])
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->get();

        $totalInvoiced = $invoices->sum('total');
        $totalPaid = $invoices->where('payment_status', 'paid')->sum('total');
        $totalOutstanding = $totalInvoiced - $totalPaid;

        $breakdown = [
            'by_status' => [
                'sent' => $invoices->where('status', 'sent')->sum('total'),
                'paid' => $invoices->where('status', 'paid')->sum('total'),
                'overdue' => $invoices->where('status', 'overdue')->sum('total'),
            ],
            'by_type' => [
                'standard' => $invoices->where('type', 'standard')->sum('total'),
                'advance_payment' => $invoices->where('type', 'advance_payment')->sum('total'),
                'balance' => $invoices->where('type', 'balance')->sum('total'),
            ],
            'invoice_count' => $invoices->count(),
        ];

        return [
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'total_outstanding' => $totalOutstanding,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Calculate costs data for a period.
     */
    protected function calculateCosts(Carbon $start, Carbon $end): array
    {
        $invoices = InvoiceReceived::whereBetween('issue_date', [$start, $end])->get();

        $warehouseCosts = $invoices->whereIn('category', ['components'])->sum('total');
        $equipmentCosts = $invoices->where('category', 'equipment')->sum('total');
        $serviceCosts = $invoices->where('category', 'services')->sum('total');
        $customsCosts = $invoices->where('category', 'customs')->sum('total');
        $generalCosts = $invoices->where('category', 'general')->sum('total');

        $totalCosts = $warehouseCosts + $equipmentCosts + $serviceCosts + $customsCosts + $generalCosts;

        $breakdown = [
            'by_category' => [
                'components' => $warehouseCosts,
                'equipment' => $equipmentCosts,
                'services' => $serviceCosts,
                'customs' => $customsCosts,
                'general' => $generalCosts,
            ],
            'by_supplier' => $invoices->groupBy('supplier_id')->map(function ($group) {
                return [
                    'supplier_name' => $group->first()->supplier_name,
                    'total' => $group->sum('total'),
                    'count' => $group->count(),
                ];
            })->toArray(),
            'invoice_count' => $invoices->count(),
        ];

        return [
            'total_costs' => $totalCosts,
            'warehouse_costs' => $warehouseCosts,
            'equipment_costs' => $equipmentCosts,
            'service_costs' => $serviceCosts,
            'customs_costs' => $customsCosts,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Calculate revenue and cost forecasts.
     */
    protected function calculateForecasts(Carbon $start, Carbon $end): array
    {
        // Forecast based on pending payment milestones
        $pendingMilestones = PaymentMilestone::where('status', 'pending')
            ->whereBetween('expected_date', [$start, $end])
            ->get();

        $forecastedRevenue = $pendingMilestones->sum('amount');

        // Simple cost forecast based on average of last 3 months
        $threeMonthsAgo = $start->copy()->subMonths(3);
        $avgCosts = InvoiceReceived::whereBetween('issue_date', [$threeMonthsAgo, $start])
            ->avg('total') ?? 0;

        return [
            'revenue' => $forecastedRevenue,
            'costs' => $avgCosts,
            'details' => [
                'pending_milestones' => $pendingMilestones->count(),
                'total_expected' => $forecastedRevenue,
            ],
        ];
    }

    /**
     * Generate quarterly analysis.
     */
    public function generateQuarterlyAnalysis(int $year, int $quarter): BillingAnalysis
    {
        $startMonth = ($quarter - 1) * 3 + 1;
        $periodStart = Carbon::create($year, $startMonth, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->addMonths(2)->endOfMonth();

        // Similar logic as monthly, but for quarter
        $revenueData = $this->calculateRevenue($periodStart, $periodEnd);
        $costsData = $this->calculateCosts($periodStart, $periodEnd);
        $forecastData = $this->calculateForecasts($periodStart, $periodEnd);

        $grossProfit = $revenueData['total_invoiced'] - $costsData['total_costs'];
        $netProfit = $revenueData['total_paid'] - $costsData['total_costs'];
        $profitMargin = $revenueData['total_invoiced'] > 0
            ? ($grossProfit / $revenueData['total_invoiced']) * 100
            : 0;

        return BillingAnalysis::updateOrCreate(
            [
                'analysis_type' => 'quarterly',
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ],
            [
                'total_revenue' => $revenueData['total_invoiced'],
                'total_invoiced' => $revenueData['total_invoiced'],
                'total_paid' => $revenueData['total_paid'],
                'total_outstanding' => $revenueData['total_outstanding'],
                'total_costs' => $costsData['total_costs'],
                'warehouse_costs' => $costsData['warehouse_costs'],
                'equipment_costs' => $costsData['equipment_costs'],
                'service_costs' => $costsData['service_costs'],
                'customs_costs' => $costsData['customs_costs'],
                'gross_profit' => $grossProfit,
                'net_profit' => $netProfit,
                'profit_margin' => round($profitMargin, 2),
                'forecasted_revenue' => $forecastData['revenue'],
                'forecasted_costs' => $forecastData['costs'],
                'details' => [
                    'revenue_breakdown' => $revenueData['breakdown'],
                    'costs_breakdown' => $costsData['breakdown'],
                    'forecast_details' => $forecastData['details'],
                ],
                'generated_at' => now(),
                'generated_by' => auth()->id(),
            ]
        );
    }

    /**
     * Generate yearly analysis.
     */
    public function generateYearlyAnalysis(int $year): BillingAnalysis
    {
        $periodStart = Carbon::create($year, 1, 1)->startOfYear();
        $periodEnd = Carbon::create($year, 12, 31)->endOfYear();

        $revenueData = $this->calculateRevenue($periodStart, $periodEnd);
        $costsData = $this->calculateCosts($periodStart, $periodEnd);
        $forecastData = $this->calculateForecasts($periodStart, $periodEnd);

        $grossProfit = $revenueData['total_invoiced'] - $costsData['total_costs'];
        $netProfit = $revenueData['total_paid'] - $costsData['total_costs'];
        $profitMargin = $revenueData['total_invoiced'] > 0
            ? ($grossProfit / $revenueData['total_invoiced']) * 100
            : 0;

        return BillingAnalysis::updateOrCreate(
            [
                'analysis_type' => 'yearly',
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ],
            [
                'total_revenue' => $revenueData['total_invoiced'],
                'total_invoiced' => $revenueData['total_invoiced'],
                'total_paid' => $revenueData['total_paid'],
                'total_outstanding' => $revenueData['total_outstanding'],
                'total_costs' => $costsData['total_costs'],
                'warehouse_costs' => $costsData['warehouse_costs'],
                'equipment_costs' => $costsData['equipment_costs'],
                'service_costs' => $costsData['service_costs'],
                'customs_costs' => $costsData['customs_costs'],
                'gross_profit' => $grossProfit,
                'net_profit' => $netProfit,
                'profit_margin' => round($profitMargin, 2),
                'forecasted_revenue' => $forecastData['revenue'],
                'forecasted_costs' => $forecastData['costs'],
                'details' => [
                    'revenue_breakdown' => $revenueData['breakdown'],
                    'costs_breakdown' => $costsData['breakdown'],
                    'forecast_details' => $forecastData['details'],
                ],
                'generated_at' => now(),
                'generated_by' => auth()->id(),
            ]
        );
    }

    /**
     * Get monthly trend data for charts (last N months).
     */
    public function getMonthlyTrend(int $months = 12): array
    {
        $data = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $analysis = BillingAnalysis::where('analysis_type', 'monthly')
                ->where('period_start', $date->copy()->startOfMonth())
                ->first();

            $data[] = [
                'month' => $date->format('M Y'),
                'month_key' => $date->format('Y-m'),
                'invoiced' => $analysis ? (float)$analysis->total_invoiced : 0,
                'paid' => $analysis ? (float)$analysis->total_paid : 0,
                'costs' => $analysis ? (float)$analysis->total_costs : 0,
                'profit' => $analysis ? (float)$analysis->net_profit : 0,
            ];
        }

        return $data;
    }

    /**
     * Export analysis to JSON for Nextcloud.
     */
    public function exportToJson(BillingAnalysis $analysis): string
    {
        return json_encode([
            'analysis_type' => $analysis->analysis_type,
            'period' => [
                'start' => $analysis->period_start->format('Y-m-d'),
                'end' => $analysis->period_end->format('Y-m-d'),
            ],
            'revenue' => [
                'total_invoiced' => (float)$analysis->total_invoiced,
                'total_paid' => (float)$analysis->total_paid,
                'outstanding' => (float)$analysis->total_outstanding,
            ],
            'costs' => [
                'total' => (float)$analysis->total_costs,
                'warehouse' => (float)$analysis->warehouse_costs,
                'equipment' => (float)$analysis->equipment_costs,
                'services' => (float)$analysis->service_costs,
                'customs' => (float)$analysis->customs_costs,
            ],
            'profit' => [
                'gross' => (float)$analysis->gross_profit,
                'net' => (float)$analysis->net_profit,
                'margin_percentage' => (float)$analysis->profit_margin,
            ],
            'forecast' => [
                'revenue' => (float)$analysis->forecasted_revenue,
                'costs' => (float)$analysis->forecasted_costs,
            ],
            'details' => $analysis->details,
            'generated_at' => $analysis->generated_at->toIso8601String(),
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Get revenue for period (wrapper method)
     */
    public function getRevenueForPeriod(Carbon $start, Carbon $end): array
    {
        return $this->calculateRevenue($start, $end);
    }

    /**
     * Get costs for period (wrapper method)
     */
    public function getCostsForPeriod(Carbon $start, Carbon $end): array
    {
        return $this->calculateCosts($start, $end);
    }

    /**
     * Calculate profit/loss for period with trend analysis
     */
    public function getProfitLoss(Carbon $start, Carbon $end): array
    {
        $revenue = $this->calculateRevenue($start, $end);
        $costs = $this->calculateCosts($start, $end);

        $grossProfit = $revenue['total_invoiced'] - $costs['total_costs'];
        $profitMargin = $revenue['total_invoiced'] > 0
            ? ($grossProfit / $revenue['total_invoiced']) * 100
            : 0;

        // Determine trend by comparing with previous period
        $periodDays = $start->diffInDays($end);
        $previousStart = $start->copy()->subDays($periodDays);
        $previousEnd = $start->copy()->subDay();

        $previousRevenue = $this->calculateRevenue($previousStart, $previousEnd);
        $previousCosts = $this->calculateCosts($previousStart, $previousEnd);
        $previousProfit = $previousRevenue['total_invoiced'] - $previousCosts['total_costs'];

        $trend = 'stable';
        if ($grossProfit > $previousProfit * 1.05) {
            $trend = 'increasing';
        } elseif ($grossProfit < $previousProfit * 0.95) {
            $trend = 'decreasing';
        }

        return [
            'revenue' => $revenue['total_invoiced'],
            'costs' => $costs['total_costs'],
            'gross_profit' => $grossProfit,
            'profit_margin' => round($profitMargin, 2),
            'trend' => $trend,
            'previous_period' => [
                'revenue' => $previousRevenue['total_invoiced'],
                'costs' => $previousCosts['total_costs'],
                'profit' => $previousProfit,
            ],
        ];
    }

    /**
     * Forecast future revenue based on payment milestones
     */
    public function forecastRevenue(int $months = 6): array
    {
        $forecast = [];
        $startDate = now()->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $monthStart = $startDate->copy()->addMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $milestones = PaymentMilestone::where('status', 'pending')
                ->whereBetween('expected_date', [$monthStart, $monthEnd])
                ->with(['project', 'quotation'])
                ->get();

            $projects = $milestones->groupBy('project_id')->map(function ($projectMilestones) {
                $project = $projectMilestones->first()->project;
                return [
                    'project_id' => $project ? $project->id : null,
                    'project_name' => $project ? $project->name : 'Unknown',
                    'amount' => $projectMilestones->sum('amount'),
                    'milestones' => $projectMilestones->count(),
                ];
            })->values();

            $forecast[$monthStart->format('Y-m')] = [
                'amount' => $milestones->sum('amount'),
                'projects' => $projects,
                'milestones_count' => $milestones->count(),
            ];
        }

        return $forecast;
    }

    /**
     * Get customer payment tracking (for progressive payments)
     */
    public function getCustomerPaymentTracking(\App\Models\Customer $customer): array
    {
        $projects = $customer->projects()->with(['quotations'])->get();

        $totalContracted = 0;
        $totalInvoiced = 0;
        $totalPaid = 0;
        $pendingMilestones = [];
        $projectsData = [];

        foreach ($projects as $project) {
            $projectContracted = $project->quotations()
                ->where('status', 'accepted')
                ->sum('total');

            $projectInvoiced = InvoiceIssued::where('project_id', $project->id)
                ->whereNotIn('status', ['draft', 'cancelled'])
                ->sum('total');

            $projectPaid = InvoiceIssued::where('project_id', $project->id)
                ->where('payment_status', 'paid')
                ->sum('total');

            $milestones = PaymentMilestone::where('project_id', $project->id)
                ->where('status', 'pending')
                ->get();

            $totalContracted += $projectContracted;
            $totalInvoiced += $projectInvoiced;
            $totalPaid += $projectPaid;

            foreach ($milestones as $milestone) {
                $pendingMilestones[] = [
                    'project' => $project->name,
                    'milestone' => $milestone->milestone_name,
                    'amount' => $milestone->amount,
                    'expected_date' => $milestone->expected_date ? $milestone->expected_date->format('Y-m-d') : null,
                ];
            }

            $projectsData[] = [
                'id' => $project->id,
                'name' => $project->name,
                'contracted' => $projectContracted,
                'invoiced' => $projectInvoiced,
                'paid' => $projectPaid,
                'outstanding' => $projectInvoiced - $projectPaid,
            ];
        }

        return [
            'total_contracted' => $totalContracted,
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'total_outstanding' => $totalInvoiced - $totalPaid,
            'pending_milestones' => $pendingMilestones,
            'projects' => $projectsData,
        ];
    }

    /**
     * Calculate project profitability
     */
    public function getProjectProfitability(\App\Models\Project $project): array
    {
        // Revenue: invoices issued for this project
        $revenue = InvoiceIssued::where('project_id', $project->id)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->sum('total');

        // Costs from components allocated
        $componentCosts = DB::table('project_component_allocations')
            ->where('project_id', $project->id)
            ->sum('total_cost') ?? 0;

        // Costs from invoices received linked to this project
        $invoiceCosts = InvoiceReceived::where('project_id', $project->id)
            ->sum('total') ?? 0;

        $totalCosts = $componentCosts + $invoiceCosts;
        $profit = $revenue - $totalCosts;
        $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

        // Get component breakdown
        $componentBreakdown = DB::table('project_component_allocations')
            ->join('components', 'project_component_allocations.component_id', '=', 'components.id')
            ->where('project_component_allocations.project_id', $project->id)
            ->select(
                'components.name',
                'components.manufacturer_part_number',
                DB::raw('SUM(project_component_allocations.quantity_allocated) as total_quantity'),
                DB::raw('SUM(project_component_allocations.total_cost) as total_cost')
            )
            ->groupBy('components.id', 'components.name', 'components.manufacturer_part_number')
            ->get()
            ->toArray();

        // Get invoice breakdown
        $invoiceBreakdown = InvoiceReceived::where('project_id', $project->id)
            ->select('invoice_number', 'supplier_name', 'category', 'total')
            ->get()
            ->toArray();

        return [
            'revenue' => $revenue,
            'costs' => $totalCosts,
            'component_costs' => $componentCosts,
            'invoice_costs' => $invoiceCosts,
            'profit' => $profit,
            'margin' => round($margin, 2),
            'component_breakdown' => $componentBreakdown,
            'invoice_breakdown' => $invoiceBreakdown,
        ];
    }
}
