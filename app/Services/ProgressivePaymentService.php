<?php

namespace App\Services;

use App\Exceptions\InvalidPaymentMilestoneException;
use App\Models\InvoiceIssued;
use App\Models\PaymentMilestone;
use App\Models\Project;
use App\Models\Quotation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProgressivePaymentService
{
    /**
     * Create payment milestones for a quotation
     * Example: createMilestones($quotation, [30, 70])
     */
    public function createMilestones(Quotation $quotation, array $percentages, ?Project $project = null): array
    {
        return DB::transaction(function () use ($quotation, $percentages, $project) {
            // Validate percentages sum to 100
            $totalPercentage = array_sum($percentages);
            if ($totalPercentage != 100) {
                throw new InvalidPaymentMilestoneException(
                    "Percentages must sum to 100. Got: {$totalPercentage}"
                );
            }

            $milestones = [];
            $sortOrder = 0;

            foreach ($percentages as $percentage) {
                $amount = ($quotation->total * $percentage) / 100;

                // Determine milestone name
                if (count($percentages) == 1) {
                    $milestoneName = "Pagamento completo {$percentage}%";
                } elseif ($sortOrder == 0) {
                    $milestoneName = "Acconto {$percentage}%";
                } elseif ($sortOrder == count($percentages) - 1) {
                    $milestoneName = "Saldo {$percentage}%";
                } else {
                    $milestoneName = "Rata {$percentage}%";
                }

                $milestone = PaymentMilestone::create([
                    'quotation_id' => $quotation->id,
                    'project_id' => $project ? $project->id : ($quotation->projects()->first() ? $quotation->projects()->first()->id : null),
                    'milestone_name' => $milestoneName,
                    'percentage' => $percentage,
                    'amount' => round($amount, 2),
                    'status' => 'pending',
                    'sort_order' => $sortOrder,
                ]);

                $milestones[] = $milestone;
                $sortOrder++;
            }

            return $milestones;
        });
    }

    /**
     * Create invoice from payment milestone
     */
    public function createInvoiceFromMilestone(PaymentMilestone $milestone): InvoiceIssued
    {
        return DB::transaction(function () use ($milestone) {
            // Validate milestone is pending
            if ($milestone->status !== 'pending') {
                throw new InvalidPaymentMilestoneException(
                    "Milestone #{$milestone->id} is already {$milestone->status}"
                );
            }

            $quotation = $milestone->quotation;
            $project = $milestone->project ?? $quotation->projects()->first();

            if (!$quotation) {
                throw new InvalidPaymentMilestoneException(
                    "Milestone #{$milestone->id} has no associated quotation"
                );
            }

            // Determine invoice type
            $sortOrder = $milestone->sort_order;
            $type = match(true) {
                $sortOrder == 0 => 'advance_payment',
                $milestone->percentage == 100 => 'standard',
                default => 'balance'
            };

            $paymentStage = match(true) {
                $sortOrder == 0 => 'deposit',
                $milestone->percentage == 100 => 'full',
                default => 'balance'
            };

            // Get previous milestone's invoice if this is a balance payment
            $relatedInvoiceId = null;
            if ($sortOrder > 0) {
                $previousMilestone = PaymentMilestone::where('quotation_id', $quotation->id)
                    ->where('sort_order', $sortOrder - 1)
                    ->where('status', 'invoiced')
                    ->first();

                if ($previousMilestone && $previousMilestone->invoice_id) {
                    $relatedInvoiceId = $previousMilestone->invoice_id;
                }
            }

            // Create invoice
            $invoice = InvoiceIssued::create([
                'customer_id' => $quotation->customer_id,
                'project_id' => $project ? $project->id : null,
                'quotation_id' => $quotation->id,
                'type' => $type,
                'payment_stage' => $paymentStage,
                'payment_percentage' => $milestone->percentage,
                'related_invoice_id' => $relatedInvoiceId,
                'issue_date' => now(),
                'due_date' => now()->addDays($quotation->customer->paymentTerm ? $quotation->customer->paymentTerm->days : 30),
                'payment_term_id' => $quotation->payment_term_id,
                'status' => 'draft',
                'payment_status' => 'unpaid',
                'subtotal' => 0, // Will be calculated from items
                'tax_rate' => $quotation->tax_rate ?? 22,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total' => $milestone->amount,
                'created_by' => auth()->id(),
            ]);

            // Add items from quotation proportionally
            $this->addItemsToInvoice($invoice, $quotation, $milestone->percentage);

            // Update milestone
            $milestone->update([
                'status' => 'invoiced',
                'invoice_id' => $invoice->id,
                'invoiced_at' => now(),
            ]);

            // Generate PDF and upload to Nextcloud (TODO)
            try {
                $this->generateInvoicePdf($invoice);
            } catch (\Exception $e) {
                Log::error('Failed to generate invoice PDF: ' . $e->getMessage());
            }

            return $invoice;
        });
    }

    /**
     * Mark milestone as paid
     */
    public function markMilestoneAsPaid(PaymentMilestone $milestone, Carbon $paidAt, ?string $paymentMethod = null): void
    {
        DB::transaction(function () use ($milestone, $paidAt, $paymentMethod) {
            // Validate milestone is invoiced
            if ($milestone->status !== 'invoiced') {
                throw new InvalidPaymentMilestoneException(
                    "Milestone #{$milestone->id} must be invoiced before marking as paid"
                );
            }

            // Update milestone
            $milestone->update([
                'status' => 'paid',
                'paid_at' => $paidAt,
            ]);

            // Update linked invoice
            if ($milestone->invoice_id) {
                $invoice = InvoiceIssued::find($milestone->invoice_id);
                if ($invoice) {
                    $invoice->markAsPaid($milestone->amount, $paymentMethod);

                    // Update customer balance if applicable
                    if ($invoice->customer) {
                        $invoice->customer->decrement('current_balance', $milestone->amount);
                    }
                }
            }
        });
    }

    /**
     * Get payment progress for project
     */
    public function getProjectPaymentProgress(Project $project): array
    {
        // Get all quotations for the project
        $quotations = $project->quotations()->where('status', 'accepted')->get();
        $totalValue = $quotations->sum('total');

        // Get all milestones for the project
        $milestones = PaymentMilestone::where('project_id', $project->id)
            ->with(['invoice', 'quotation'])
            ->orderBy('sort_order')
            ->get();

        $totalMilestones = $milestones->count();
        $invoicedAmount = $milestones->where('status', '!=', 'pending')->sum('amount');
        $paidAmount = $milestones->where('status', 'paid')->sum('amount');
        $pendingAmount = $milestones->where('status', 'pending')->sum('amount');

        $completionPercentage = $totalValue > 0 ? ($paidAmount / $totalValue) * 100 : 0;

        $milestonesDetail = $milestones->map(function ($milestone) {
            return [
                'id' => $milestone->id,
                'name' => $milestone->milestone_name,
                'percentage' => (float) $milestone->percentage,
                'amount' => (float) $milestone->amount,
                'status' => $milestone->status,
                'expected_date' => $milestone->expected_date ? $milestone->expected_date->format('Y-m-d') : null,
                'invoiced_at' => $milestone->invoiced_at ? $milestone->invoiced_at->format('Y-m-d') : null,
                'paid_at' => $milestone->paid_at ? $milestone->paid_at->format('Y-m-d') : null,
                'invoice_number' => $milestone->invoice ? $milestone->invoice->invoice_number : null,
            ];
        });

        return [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'total_value' => $totalValue,
            'total_milestones' => $totalMilestones,
            'invoiced_amount' => $invoicedAmount,
            'paid_amount' => $paidAmount,
            'pending_amount' => $pendingAmount,
            'completion_percentage' => round($completionPercentage, 2),
            'milestones' => $milestonesDetail,
        ];
    }

    /**
     * Get overdue milestones
     */
    public function getOverdueMilestones(): array
    {
        $milestones = PaymentMilestone::where('status', 'pending')
            ->whereNotNull('expected_date')
            ->where('expected_date', '<', now())
            ->with(['project', 'quotation', 'quotation.customer'])
            ->get();

        return $milestones->map(function ($milestone) {
            return [
                'id' => $milestone->id,
                'milestone_name' => $milestone->milestone_name,
                'amount' => (float) $milestone->amount,
                'expected_date' => $milestone->expected_date->format('Y-m-d'),
                'days_overdue' => now()->diffInDays($milestone->expected_date),
                'project' => $milestone->project ? $milestone->project->name : 'N/A',
                'customer' => $milestone->quotation && $milestone->quotation->customer
                    ? $milestone->quotation->customer->company_name
                    : 'N/A',
            ];
        })->toArray();
    }

    /**
     * Cancel milestone (if not yet invoiced)
     */
    public function cancelMilestone(PaymentMilestone $milestone): void
    {
        if ($milestone->status !== 'pending') {
            throw new InvalidPaymentMilestoneException(
                "Cannot cancel milestone #{$milestone->id} - it is already {$milestone->status}"
            );
        }

        $milestone->delete();
    }

    /**
     * Update milestone expected date
     */
    public function updateExpectedDate(PaymentMilestone $milestone, Carbon $newDate): void
    {
        if ($milestone->status !== 'pending') {
            throw new InvalidPaymentMilestoneException(
                "Cannot update expected date for milestone #{$milestone->id} - it is already {$milestone->status}"
            );
        }

        $milestone->update(['expected_date' => $newDate]);
    }

    /**
     * Add items to invoice from quotation (proportionally based on percentage)
     */
    protected function addItemsToInvoice(InvoiceIssued $invoice, Quotation $quotation, float $percentage): void
    {
        $quotationItems = $quotation->items;

        foreach ($quotationItems as $item) {
            $invoiceItem = $invoice->items()->create([
                'description' => $item->description,
                'quantity' => $item->quantity * ($percentage / 100),
                'unit_price' => $item->unit_price,
                'discount_percentage' => $item->discount_percentage ?? 0,
                'tax_rate' => $item->tax_rate ?? 22,
                'component_id' => $item->component_id,
                'sort_order' => $item->sort_order,
            ]);

            // Totals will be calculated by the item's boot method
        }

        // Recalculate invoice totals
        $invoice->calculateTotals();
    }

    /**
     * Generate invoice PDF and upload to Nextcloud
     */
    protected function generateInvoicePdf(InvoiceIssued $invoice): void
    {
        // TODO: Integrate with PDF generation service and Nextcloud
        // Path: Clienti/{CUSTOMER}/04_Fatturazione/Fatture_Emesse/{YEAR}/{INVOICE}.pdf

        $customer = $invoice->customer;
        $year = $invoice->issue_date->year;
        $path = "Clienti/{$customer->company_name}/04_Fatturazione/Fatture_Emesse/{$year}/{$invoice->invoice_number}.pdf";

        Log::info("Invoice PDF should be generated at: {$path}");

        // Update invoice with path
        $invoice->update([
            'nextcloud_path' => $path,
            'pdf_generated_at' => now(),
        ]);
    }
}
