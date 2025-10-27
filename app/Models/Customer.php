<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;

class Customer extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'code',
        'company_name',
        'customer_type_id',
        'payment_term_id',
        'vat_number',
        'tax_code',
        'sdi_code',
        'email',
        'pec_email',
        'phone',
        'mobile',
        'address',
        'city',
        'postal_code',
        'province',
        'country',
        'notes',
        'folder',
        'is_active',
        // Billing fields
        'billing_email',
        'billing_contact_name',
        'billing_phone',
        'default_payment_terms',
        'credit_limit',
        'current_balance',
        // Nextcloud fields
        'nextcloud_folder_created',
        'nextcloud_base_path',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($customer) {
            if (!$customer->code) {
                $customer->code = static::generateNextCode();
            }
        });
    }

    protected static function generateNextCode(): string
    {
        $lastCustomer = static::orderBy('id', 'desc')->first();
        $nextNumber = $lastCustomer ? (intval(substr($lastCustomer->code, 1)) + 1) : 1;
        return 'C' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    protected $casts = [
        'is_active' => 'boolean',
        'credit_limit' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'nextcloud_folder_created' => 'boolean',
    ];

    /**
     * Get the customer type.
     */
    public function customerType(): BelongsTo
    {
        return $this->belongsTo(CustomerType::class);
    }

    /**
     * Get the payment term.
     */
    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    /**
     * Get all projects for this customer.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get all quotations for this customer.
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    /**
     * Get all documents for this customer.
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Get all invoices issued to this customer.
     */
    public function invoicesIssued(): HasMany
    {
        return $this->hasMany(InvoiceIssued::class);
    }

    /**
     * Get all invoices received related to this customer.
     */
    public function invoicesReceived(): HasMany
    {
        return $this->hasMany(InvoiceReceived::class);
    }

    /**
     * Get all contracts for this customer.
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(CustomerContract::class);
    }

    /**
     * Get all F24 forms for this customer.
     */
    public function f24Forms(): HasMany
    {
        return $this->hasMany(F24Form::class);
    }

    /**
     * Get the display name (company name).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->company_name;
    }

    /**
     * Get the full address.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->postal_code,
            $this->city,
            $this->province,
            $this->country,
        ]);
        
        return implode(', ', $parts);
    }

    /**
     * Get the searchable array for Scout.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'company_name' => $this->company_name,
            'vat_number' => $this->vat_number,
            'tax_code' => $this->tax_code,
            'email' => $this->email,
            'city' => $this->city,
        ];
    }

    /**
     * Scope for active customers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for customers by type.
     */
    public function scopeByType($query, $typeId)
    {
        return $query->where('customer_type_id', $typeId);
    }
}