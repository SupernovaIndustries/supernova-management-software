<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMilestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'quotation_id',
        'milestone_name',
        'percentage',
        'amount',
        'status',
        'invoice_id',
        'expected_date',
        'invoiced_at',
        'paid_at',
        'sort_order',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'amount' => 'decimal:2',
        'expected_date' => 'date',
        'invoiced_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(InvoiceIssued::class, 'invoice_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInvoiced($query)
    {
        return $query->where('status', 'invoiced');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}
