<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Quotation extends Model
{
    use HasFactory;
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($quotation) {
            if (!$quotation->is_manual_entry && !$quotation->number) {
                $quotation->number = static::generateQuotationNumber();
                $quotation->incremental_id = static::getNextIncrementalId();
            }
            
            // Calculate financial fields if not already set
            $quotation->calculateTotals();
        });
        
        static::updating(function ($quotation) {
            // Recalculate totals when updating
            $quotation->calculateTotals();
        });
    }
    
    /**
     * Generate quotation number in format XXX-YY
     */
    protected static function generateQuotationNumber(): string
    {
        $year = date('y');
        $incrementalId = static::getNextIncrementalId();
        
        return str_pad($incrementalId, 3, '0', STR_PAD_LEFT) . '-' . $year;
    }
    
    /**
     * Get next incremental ID starting from 7
     */
    protected static function getNextIncrementalId(): int
    {
        $lastId = static::max('incremental_id') ?? 6;
        return $lastId + 1;
    }

    protected $fillable = [
        'number',
        'incremental_id',
        'customer_id',
        'payment_term_id',
        'project_id',
        'user_id',
        'status',
        'payment_status',
        'date',
        'valid_until',
        'currency',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'discount_rate',
        'discount_amount',
        'total',
        'materials_deposit',
        'development_balance',
        'notes',
        'terms_conditions',
        'payment_terms',
        'sent_at',
        'accepted_at',
        'rejected_at',
        'rejection_reason',
        'deposit_paid_at',
        'balance_paid_at',
        'is_manual_entry',
        'boards_quantity',
        'pdf_path',
        'pdf_uploaded_manually',
        'pdf_generated_at',
        'nextcloud_path',
    ];

    protected $casts = [
        'incremental_id' => 'integer',
        'date' => 'date',
        'valid_until' => 'date',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_rate' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'materials_deposit' => 'decimal:2',
        'development_balance' => 'decimal:2',
        'pdf_uploaded_manually' => 'boolean',
        'pdf_generated_at' => 'datetime',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'deposit_paid_at' => 'datetime',
        'balance_paid_at' => 'datetime',
        'is_manual_entry' => 'boolean',
        'boards_quantity' => 'integer',
    ];

    /**
     * Get the customer for this quotation.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the direct project (legacy relationship via project_id).
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get linked projects (unified many-to-many relation).
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_quotation')
            ->withTimestamps();
    }
    
    /**
     * Get linked projects (alias for backward compatibility).
     */
    public function linkedProjects(): BelongsToMany
    {
        return $this->projects();
    }

    /**
     * Get the user who created this quotation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the payment term for this quotation.
     */
    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    /**
     * Get all items for this quotation.
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    /**
     * Get all documents for this quotation.
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Check if quotation is expired.
     */
    public function isExpired(): bool
    {
        return $this->valid_until->isPast() && $this->status === 'sent';
    }

    /**
     * Check if quotation can be edited.
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft', 'rejected']);
    }

    /**
     * Get the status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft' => 'gray',
            'sent' => 'primary',
            'accepted' => 'success',
            'rejected' => 'danger',
            'expired' => 'warning',
            default => 'secondary',
        };
    }

    /**
     * Calculate totals from items.
     */
    public function calculateTotals(): void
    {
        // Calculate subtotal from items - always recalculate from scratch
        $itemsTotal = $this->relationLoaded('items') 
            ? $this->items->sum('total') 
            : $this->items()->sum('total');
        
        // Ensure we have default values
        $discountRate = $this->discount_rate ?? 0;
        $taxRate = $this->tax_rate ?? 22; // Default Italian VAT
        
        // Calculate amounts based on items total
        $discountAmount = $itemsTotal * ($discountRate / 100);
        $taxableAmount = $itemsTotal - $discountAmount;
        $taxAmount = $taxableAmount * ($taxRate / 100);
        
        // Set all calculated attributes directly to avoid update() loop
        $this->attributes['subtotal'] = round($itemsTotal, 2);
        $this->attributes['discount_amount'] = round($discountAmount, 2);
        $this->attributes['tax_amount'] = round($taxAmount, 2);
        $this->attributes['total'] = round($taxableAmount + $taxAmount, 2);
    }

    /**
     * Scope for draft quotations.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope for sent quotations.
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope for accepted quotations.
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }
}