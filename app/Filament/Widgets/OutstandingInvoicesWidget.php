<?php

namespace App\Filament\Widgets;

use App\Models\InvoiceIssued;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class OutstandingInvoicesWidget extends BaseWidget
{
    protected static ?string $heading = 'Fatture in Sospeso';
    protected static ?int $sort = 3;

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
                InvoiceIssued::query()
                    ->where('payment_status', '!=', 'paid')
                    ->whereNotIn('status', ['draft', 'cancelled'])
                    ->orderBy('due_date', 'asc')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Numero')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.company_name')
                    ->label('Cliente')
                    ->searchable()
                    ->limit(20)
                    ->tooltip(function (InvoiceIssued $record): ?string {
                        return $record->customer->company_name;
                    }),

                Tables\Columns\TextColumn::make('total')
                    ->label('Importo')
                    ->money('EUR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->sortable()
                    ->badge()
                    ->color(fn(InvoiceIssued $record) => $record->due_date->isPast() ? 'danger' : 'warning'),

                Tables\Columns\TextColumn::make('days_overdue')
                    ->label('Giorni')
                    ->getStateUsing(fn(InvoiceIssued $record) =>
                        $record->due_date->isPast()
                            ? '+' . $record->due_date->diffInDays(now())
                            : '-' . now()->diffInDays($record->due_date)
                    )
                    ->badge()
                    ->color(fn(InvoiceIssued $record) => $record->due_date->isPast() ? 'danger' : 'success'),
            ])
            ->paginated(false)
            ->striped();
    }
}
