<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'analysis_type',
        'period_start',
        'period_end',
        'total_revenue',
        'total_invoiced',
        'total_paid',
        'total_outstanding',
        'total_costs',
        'warehouse_costs',
        'equipment_costs',
        'service_costs',
        'customs_costs',
        'gross_profit',
        'net_profit',
        'profit_margin',
        'forecasted_revenue',
        'forecasted_costs',
        'details',
        'generated_at',
        'generated_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_revenue' => 'decimal:2',
        'total_invoiced' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'total_outstanding' => 'decimal:2',
        'total_costs' => 'decimal:2',
        'warehouse_costs' => 'decimal:2',
        'equipment_costs' => 'decimal:2',
        'service_costs' => 'decimal:2',
        'customs_costs' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'net_profit' => 'decimal:2',
        'profit_margin' => 'decimal:2',
        'forecasted_revenue' => 'decimal:2',
        'forecasted_costs' => 'decimal:2',
        'details' => 'array',
        'generated_at' => 'datetime',
    ];

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function scopeMonthly($query)
    {
        return $query->where('analysis_type', 'monthly');
    }

    public function scopeQuarterly($query)
    {
        return $query->where('analysis_type', 'quarterly');
    }

    public function scopeYearly($query)
    {
        return $query->where('analysis_type', 'yearly');
    }

    public function scopeForPeriod($query, $start, $end)
    {
        return $query->where('period_start', '>=', $start)
            ->where('period_end', '<=', $end);
    }
}
