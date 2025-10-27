<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class F24Form extends Model
{
    use HasFactory;

    protected $table = 'f24_forms';

    protected $fillable = [
        'form_number',
        'type',
        'reference_month',
        'reference_year',
        'total_amount',
        'payment_date',
        'due_date',
        'customer_id',
        'nextcloud_path',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'reference_month' => 'integer',
        'reference_year' => 'integer',
        'total_amount' => 'decimal:2',
        'payment_date' => 'date',
        'due_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($form) {
            if (!$form->form_number) {
                $form->form_number = static::generateFormNumber();
            }

            if (!isset($form->created_by)) {
                $form->created_by = auth()->id();
            }
        });
    }

    /**
     * Generate form number in format F24-YYYY-XXX
     */
    protected static function generateFormNumber(): string
    {
        $year = date('Y');
        $lastId = static::whereYear('created_at', $year)->max('id') ?? 0;
        $incrementalId = $lastId + 1;

        return 'F24-' . $year . '-' . str_pad($incrementalId, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Get the customer
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the creator
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get type color for badge
     */
    public function getTypeColorAttribute(): string
    {
        return match($this->type) {
            'imu' => 'warning',
            'tasi' => 'warning',
            'iva' => 'danger',
            'inps' => 'info',
            'inail' => 'info',
            'irpef' => 'success',
            'other' => 'gray',
            default => 'secondary',
        };
    }
}
