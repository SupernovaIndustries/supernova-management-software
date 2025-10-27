<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\InvoiceIssued;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class TopCustomersWidget extends BaseWidget
{
    protected static ?string $heading = 'Top Clienti per Fatturato';
    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'sm' => 'full',
        'md' => 2,
        'lg' => 1,
        'xl' => 1,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Customer::query()
                    ->select('customers.*')
                    ->selectRaw('COALESCE(SUM(invoice_issueds.total), 0) as total_invoiced')
                    ->selectRaw("COALESCE(SUM(CASE WHEN invoice_issueds.payment_status = 'paid' THEN invoice_issueds.total ELSE 0 END), 0) as total_paid")
                    ->selectRaw("COALESCE(SUM(CASE WHEN invoice_issueds.payment_status != 'paid' THEN invoice_issueds.total ELSE 0 END), 0) as total_outstanding")
                    ->leftJoin('invoice_issueds', function ($join) {
                        $join->on('customers.id', '=', 'invoice_issueds.customer_id')
                            ->whereYear('invoice_issueds.issue_date', now()->year)
                            ->whereNotIn('invoice_issueds.status', ['draft', 'cancelled']);
                    })
                    ->groupBy('customers.id')
                    ->orderByDesc('total_invoiced')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('company_name')
                    ->label('Cliente')
                    ->searchable()
                    ->limit(25)
                    ->tooltip(function ($record): ?string {
                        return $record->company_name;
                    }),

                Tables\Columns\TextColumn::make('total_invoiced')
                    ->label('Fatturato')
                    ->money('EUR')
                    ->sortable()
                    ->getStateUsing(fn($record) => $record->total_invoiced ?? 0),

                Tables\Columns\TextColumn::make('total_paid')
                    ->label('Incassato')
                    ->money('EUR')
                    ->getStateUsing(fn($record) => $record->total_paid ?? 0)
                    ->color('success'),

                Tables\Columns\TextColumn::make('total_outstanding')
                    ->label('Da Incassare')
                    ->money('EUR')
                    ->getStateUsing(fn($record) => $record->total_outstanding ?? 0)
                    ->color(fn($record) => ($record->total_outstanding ?? 0) > 0 ? 'warning' : 'success'),
            ])
            ->paginated(false)
            ->striped();
    }
}
