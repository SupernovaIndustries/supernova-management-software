<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fattura {{ $invoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
        }

        .container {
            padding: 20px;
        }

        .header {
            margin-bottom: 30px;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 15px;
        }

        .header-grid {
            display: table;
            width: 100%;
        }

        .header-left {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .header-right {
            display: table-cell;
            width: 50%;
            text-align: right;
            vertical-align: top;
        }

        .company-name {
            font-size: 18pt;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 5px;
        }

        .company-info {
            font-size: 9pt;
            color: #666;
            line-height: 1.6;
        }

        .invoice-title {
            font-size: 24pt;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 5px;
        }

        .invoice-number {
            font-size: 14pt;
            color: #666;
        }

        .info-section {
            margin-bottom: 25px;
        }

        .info-grid {
            display: table;
            width: 100%;
        }

        .info-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 15px;
        }

        .info-box {
            border: 1px solid #e5e7eb;
            padding: 12px;
            background-color: #f9fafb;
            margin-bottom: 10px;
        }

        .info-box-title {
            font-weight: bold;
            font-size: 11pt;
            color: #1e40af;
            margin-bottom: 8px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 4px;
        }

        .info-row {
            padding: 3px 0;
        }

        .info-label {
            font-weight: bold;
            color: #666;
            display: inline-block;
            width: 40%;
        }

        .info-value {
            display: inline-block;
            width: 58%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        thead th {
            background-color: #2563eb;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-weight: bold;
            font-size: 9pt;
        }

        thead th.text-right {
            text-align: right;
        }

        thead th.text-center {
            text-align: center;
        }

        tbody td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9pt;
        }

        tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }

        tbody td.text-right {
            text-align: right;
        }

        tbody td.text-center {
            text-align: center;
        }

        .totals-section {
            margin-top: 20px;
            float: right;
            width: 45%;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 6px 10px;
            font-size: 10pt;
        }

        .totals-table td.label {
            text-align: right;
            font-weight: bold;
            color: #666;
            width: 60%;
        }

        .totals-table td.value {
            text-align: right;
            width: 40%;
        }

        .totals-table tr.subtotal td {
            border-top: 1px solid #e5e7eb;
        }

        .totals-table tr.total td {
            border-top: 2px solid #2563eb;
            font-size: 12pt;
            font-weight: bold;
            color: #1e40af;
            padding-top: 8px;
        }

        .notes-section {
            clear: both;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .notes-title {
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 8px;
        }

        .notes-content {
            font-size: 9pt;
            color: #666;
            line-height: 1.6;
        }

        .footer {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            text-align: center;
            font-size: 8pt;
            color: #999;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }

        .payment-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 9pt;
            font-weight: bold;
            margin-top: 5px;
        }

        .payment-badge.paid {
            background-color: #dcfce7;
            color: #166534;
        }

        .payment-badge.unpaid {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .payment-badge.partial {
            background-color: #fef3c7;
            color: #92400e;
        }
    </style>
</head>
<body>
    <div class="container">
        {{-- Header --}}
        <div class="header">
            <div class="header-grid">
                <div class="header-left">
                    <div class="company-name">{{ $company->company_name ?? 'Supernova SRL' }}</div>
                    <div class="company-info">
                        @if($company)
                            {{ $company->address }}<br>
                            {{ $company->postal_code }} {{ $company->city }} ({{ $company->province }})<br>
                            P.IVA: {{ $company->vat_number }}<br>
                            @if($company->email)Email: {{ $company->email }}<br>@endif
                            @if($company->phone)Tel: {{ $company->phone }}@endif
                        @else
                            Via Example 123<br>
                            00100 Roma (RM)<br>
                            P.IVA: IT12345678901
                        @endif
                    </div>
                </div>
                <div class="header-right">
                    <div class="invoice-title">FATTURA</div>
                    <div class="invoice-number">N. {{ $invoice->invoice_number }}</div>
                    @if($invoice->type !== 'standard')
                        <div style="margin-top: 5px; font-size: 9pt; color: #666;">
                            @if($invoice->type === 'advance_payment')
                                (Fattura Acconto - {{ $invoice->payment_percentage }}%)
                            @elseif($invoice->type === 'balance')
                                (Fattura Saldo - {{ $invoice->payment_percentage }}%)
                            @elseif($invoice->type === 'credit_note')
                                (Nota di Credito)
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Info Section --}}
        <div class="info-section">
            <div class="info-grid">
                <div class="info-col">
                    <div class="info-box">
                        <div class="info-box-title">CLIENTE</div>
                        <div class="info-row">
                            <strong>{{ $invoice->customer->company_name }}</strong>
                        </div>
                        @if($invoice->customer->vat_number)
                        <div class="info-row">P.IVA: {{ $invoice->customer->vat_number }}</div>
                        @endif
                        @if($invoice->customer->tax_code)
                        <div class="info-row">C.F.: {{ $invoice->customer->tax_code }}</div>
                        @endif
                        @if($invoice->customer->address)
                        <div class="info-row">{{ $invoice->customer->address }}</div>
                        @endif
                        @if($invoice->customer->city)
                        <div class="info-row">
                            {{ $invoice->customer->postal_code }} {{ $invoice->customer->city }}
                            @if($invoice->customer->province)({{ $invoice->customer->province }})@endif
                        </div>
                        @endif
                        @if($invoice->customer->sdi_code)
                        <div class="info-row">Codice SDI: {{ $invoice->customer->sdi_code }}</div>
                        @endif
                        @if($invoice->customer->pec_email)
                        <div class="info-row">PEC: {{ $invoice->customer->pec_email }}</div>
                        @endif
                    </div>
                </div>
                <div class="info-col">
                    <div class="info-box">
                        <div class="info-box-title">DETTAGLI FATTURA</div>
                        <div class="info-row">
                            <span class="info-label">Data Emissione:</span>
                            <span class="info-value">{{ $invoice->issue_date->format('d/m/Y') }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Scadenza:</span>
                            <span class="info-value">{{ $invoice->due_date->format('d/m/Y') }}</span>
                        </div>
                        @if($invoice->paymentTerm)
                        <div class="info-row">
                            <span class="info-label">Pagamento:</span>
                            <span class="info-value">{{ $invoice->paymentTerm->name }}</span>
                        </div>
                        @endif
                        @if($invoice->project)
                        <div class="info-row">
                            <span class="info-label">Progetto:</span>
                            <span class="info-value">{{ $invoice->project->code }}</span>
                        </div>
                        @endif
                        @if($invoice->quotation)
                        <div class="info-row">
                            <span class="info-label">Preventivo:</span>
                            <span class="info-value">{{ $invoice->quotation->number }}</span>
                        </div>
                        @endif
                        <div class="info-row">
                            <span class="info-label">Stato Pagamento:</span>
                            <span class="payment-badge {{ $invoice->payment_status }}">
                                @if($invoice->payment_status === 'paid')
                                    PAGATA
                                @elseif($invoice->payment_status === 'partial')
                                    PAGAMENTO PARZIALE
                                @else
                                    NON PAGATA
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Items Table --}}
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 45%;">Descrizione</th>
                    <th class="text-center" style="width: 10%;">Q.tà</th>
                    <th class="text-right" style="width: 13%;">Prezzo Unit.</th>
                    @if($invoice->items->where('discount_percentage', '>', 0)->count() > 0)
                    <th class="text-center" style="width: 7%;">Sconto</th>
                    @endif
                    <th class="text-center" style="width: 7%;">IVA</th>
                    <th class="text-right" style="width: 13%;">Totale</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        {{ $item->description }}
                        @if($item->component)
                            <br><small style="color: #666;">Cod: {{ $item->component->code }}</small>
                        @endif
                    </td>
                    <td class="text-center">{{ number_format($item->quantity, 2) }}</td>
                    <td class="text-right">€ {{ number_format($item->unit_price, 2) }}</td>
                    @if($invoice->items->where('discount_percentage', '>', 0)->count() > 0)
                    <td class="text-center">
                        @if($item->discount_percentage > 0)
                            {{ number_format($item->discount_percentage, 0) }}%
                        @else
                            -
                        @endif
                    </td>
                    @endif
                    <td class="text-center">{{ number_format($item->tax_rate, 0) }}%</td>
                    <td class="text-right">€ {{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totals --}}
        <div class="totals-section">
            <table class="totals-table">
                <tr class="subtotal">
                    <td class="label">Imponibile:</td>
                    <td class="value">€ {{ number_format($invoice->subtotal, 2) }}</td>
                </tr>
                @if($invoice->discount_amount > 0)
                <tr>
                    <td class="label">Sconto:</td>
                    <td class="value">- € {{ number_format($invoice->discount_amount, 2) }}</td>
                </tr>
                @endif
                <tr>
                    <td class="label">IVA ({{ number_format($invoice->tax_rate, 0) }}%):</td>
                    <td class="value">€ {{ number_format($invoice->tax_amount, 2) }}</td>
                </tr>
                <tr class="total">
                    <td class="label">TOTALE:</td>
                    <td class="value">€ {{ number_format($invoice->total, 2) }}</td>
                </tr>
            </table>
        </div>

        {{-- Notes --}}
        @if($invoice->notes || $invoice->payment_method)
        <div class="notes-section">
            @if($invoice->notes)
            <div class="notes-title">Note:</div>
            <div class="notes-content">{!! nl2br(e($invoice->notes)) !!}</div>
            @endif

            @if($invoice->payment_method && $invoice->payment_status === 'paid')
            <div style="margin-top: 10px;">
                <strong>Metodo di Pagamento:</strong> {{ ucfirst(str_replace('_', ' ', $invoice->payment_method)) }}
                @if($invoice->paid_at)
                    <br><strong>Pagato il:</strong> {{ $invoice->paid_at->format('d/m/Y') }}
                @endif
            </div>
            @endif
        </div>
        @endif

        {{-- Footer --}}
        <div class="footer">
            Documento generato elettronicamente • {{ now()->format('d/m/Y H:i') }}
            @if($company && $company->website)
                • {{ $company->website }}
            @endif
        </div>
    </div>
</body>
</html>
