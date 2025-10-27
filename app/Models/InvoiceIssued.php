<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceIssued extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'incremental_id',
        'customer_id',
        'project_id',
        'quotation_id',
        'type',
        'issue_date',
        'due_date',
        'payment_term_id',
        'payment_term_tranche_id',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'discount_amount',
        'total',
        'payment_stage',
        'payment_percentage',
        'related_invoice_id',
        'status',
        'payment_status',
        'amount_paid',
        'paid_at',
        'payment_method',
        'nextcloud_path',
        'pdf_generated_at',
        'notes',
        'internal_notes',
        'created_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'payment_percentage' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'paid_at' => 'datetime',
        'pdf_generated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (!$invoice->invoice_number) {
                $invoice->invoice_number = static::generateInvoiceNumber();
                $invoice->incremental_id = static::getNextIncrementalId();
            }
        });
    }

    /**
     * Generate invoice number (format: INV-YYYY-NNNN)
     */
    protected static function generateInvoiceNumber(): string
    {
        $year = now()->year;
        $lastInvoice = static::whereYear('created_at', $year)
            ->orderBy('incremental_id', 'desc')
            ->first();

        $nextNumber = $lastInvoice ? $lastInvoice->incremental_id + 1 : 1;
        return 'INV-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get next incremental ID for current year
     */
    protected static function getNextIncrementalId(): int
    {
        $year = now()->year;
        $lastInvoice = static::whereYear('created_at', $year)
            ->orderBy('incremental_id', 'desc')
            ->first();

        return $lastInvoice ? $lastInvoice->incremental_id + 1 : 1;
    }

    /**
     * Get the customer for this invoice.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the project for this invoice.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the quotation for this invoice.
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * Get the payment term.
     */
    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    /**
     * Get the payment term tranche.
     */
    public function paymentTermTranche(): BelongsTo
    {
        return $this->belongsTo(PaymentTermTranche::class);
    }

    /**
     * Get all items for this invoice.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceIssuedItem::class, 'invoice_id');
    }

    /**
     * Get the related invoice (for progressive payments).
     */
    public function relatedInvoice(): BelongsTo
    {
        return $this->belongsTo(InvoiceIssued::class, 'related_invoice_id');
    }

    /**
     * Get the payment milestone for this invoice.
     */
    public function paymentMilestone(): HasMany
    {
        return $this->hasMany(PaymentMilestone::class, 'invoice_id');
    }

    /**
     * Get the user who created this invoice.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if invoice is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date < now() && $this->payment_status !== 'paid';
    }

    /**
     * Calculate remaining amount.
     */
    public function getRemainingAmountAttribute(): float
    {
        return $this->total - $this->amount_paid;
    }

    /**
     * Scope for unpaid invoices.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', '!=', 'paid');
    }

    /**
     * Scope for overdue invoices.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->where('payment_status', '!=', 'paid');
    }

    /**
     * Scope for draft invoices.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope for sent invoices.
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope for paid invoices.
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    /**
     * Scope for invoices by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Calculate totals from items.
     */
    public function calculateTotals(): void
    {
        $items = $this->items;

        $this->subtotal = $items->sum('subtotal');
        $this->tax_amount = $items->sum('tax_amount');
        $this->total = $items->sum('total');

        if ($this->discount_amount) {
            $this->total -= $this->discount_amount;
        }

        $this->save();
    }

    /**
     * Mark invoice as paid.
     */
    public function markAsPaid(float $amount = null, string $paymentMethod = null): void
    {
        $this->amount_paid = $amount ?? $this->total;
        $this->payment_status = 'paid';
        $this->paid_at = now();
        $this->payment_method = $paymentMethod;
        $this->save();
    }

    /**
     * Check if this invoice is for a payment tranche.
     */
    public function isTrancheInvoice(): bool
    {
        return $this->payment_term_tranche_id !== null;
    }

    /**
     * Get the tranche description (e.g., "Acconto 30%")
     */
    public function getTrancheDescriptionAttribute(): ?string
    {
        if (!$this->payment_term_tranche_id) {
            return null;
        }

        $tranche = $this->paymentTermTranche;
        return $tranche ? $tranche->full_description : null;
    }

    /**
     * Calculate invoice total based on tranche percentage and base amount.
     *
     * @param float $baseAmount The total project/quotation amount
     * @return float The amount for this tranche
     */
    public function calculateTrancheAmount(float $baseAmount): float
    {
        if (!$this->payment_term_tranche_id) {
            return $baseAmount;
        }

        $tranche = $this->paymentTermTranche;
        if (!$tranche) {
            return $baseAmount;
        }

        return $baseAmount * ($tranche->percentage / 100);
    }

    /**
     * Get all invoices for the same project/quotation that are part of tranches.
     */
    public function getRelatedTrancheInvoices()
    {
        $query = static::query();

        if ($this->project_id) {
            $query->where('project_id', $this->project_id);
        } elseif ($this->quotation_id) {
            $query->where('quotation_id', $this->quotation_id);
        } else {
            return collect();
        }

        return $query->whereNotNull('payment_term_tranche_id')
            ->where('id', '!=', $this->id)
            ->with('paymentTermTranche')
            ->orderBy('id')
            ->get();
    }

    /**
     * Check if all tranches for this project/quotation have been invoiced.
     */
    public function areAllTranchesInvoiced(): bool
    {
        if (!$this->payment_term_tranche_id) {
            return false;
        }

        $paymentTerm = $this->paymentTermTranche->paymentTerm;
        $totalTranches = $paymentTerm->tranches->count();

        $invoicedTranches = static::where(function ($query) {
            if ($this->project_id) {
                $query->where('project_id', $this->project_id);
            } else {
                $query->where('quotation_id', $this->quotation_id);
            }
        })
        ->whereNotNull('payment_term_tranche_id')
        ->distinct('payment_term_tranche_id')
        ->count();

        return $invoicedTranches >= $totalTranches;
    }
}
