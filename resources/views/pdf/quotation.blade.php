@php
    $companyProfile = \App\Models\CompanyProfile::current();
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preventivo {{ $quotation->number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 30px;
            border-bottom: 2px solid #1A4A4A;
            padding-bottom: 20px;
        }
        .header-logo {
            display: table-cell;
            width: 30%;
            vertical-align: middle;
        }
        .header-info {
            display: table-cell;
            width: 70%;
            vertical-align: middle;
            padding-left: 20px;
        }
        .logo {
            max-width: 220px;
            max-height: 100px;
            width: auto;
            height: auto;
        }
        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #1A4A4A;
            margin-bottom: 5px;
        }
        .company-details {
            font-size: 10px;
            color: #666;
            line-height: 1.4;
        }
        .row {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .col-6 {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 15px;
        }
        .col-6:last-child {
            padding-right: 0;
            padding-left: 15px;
        }
        .info-block {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .info-block h3 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #1A4A4A;
            font-size: 14px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 5px;
        }
        .info-row {
            margin-bottom: 8px;
            display: table;
            width: 100%;
        }
        .info-label {
            font-weight: bold;
            display: table-cell;
            width: 140px;
            padding-right: 10px;
        }
        .info-value {
            display: table-cell;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table thead {
            background-color: #1A4A4A;
            color: white;
        }
        table th {
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }
        table td {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals {
            margin-top: 20px;
            text-align: right;
        }
        .total-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 5px;
        }
        .total-label {
            font-weight: bold;
            margin-right: 20px;
            min-width: 150px;
            text-align: right;
        }
        .total-value {
            min-width: 100px;
            text-align: right;
        }
        .total-row.final {
            font-size: 16px;
            color: #1A4A4A;
            border-top: 2px solid #1A4A4A;
            padding-top: 10px;
            margin-top: 10px;
        }
        .payment-terms {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-top: 30px;
            page-break-before: always;
        }
        .payment-terms h4 {
            margin-top: 0;
            color: #1A4A4A;
        }
        .tranche-item {
            margin-bottom: 8px;
            padding: 8px;
            background-color: #fff;
            border-radius: 3px;
        }
        .validity-notice {
            background-color: #E0FFFF;
            border: 1px solid #00BFBF;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .clauses-section {
            margin-top: 30px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            font-size: 11px;
        }
        .clauses-section h4 {
            margin-top: 0;
            color: #1A4A4A;
        }
        .signatures-section {
            margin-top: 40px;
            page-break-inside: avoid;
        }
        .signature-declaration {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            font-size: 11px;
        }
        .signature-row {
            display: table;
            width: 100%;
            margin-top: 30px;
        }
        .signature-box {
            display: table-cell;
            width: 48%;
            padding: 15px;
        }
        .signature-box:first-child {
            margin-right: 4%;
        }
        .signature-label {
            font-weight: bold;
            color: #1A4A4A;
            margin-bottom: 10px;
        }
        .signature-name {
            margin-top: 40px;
            border-top: 1px solid #333;
            padding-top: 5px;
            font-size: 11px;
        }
        .signature-role {
            font-size: 10px;
            color: #666;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        @page {
            margin: 20mm;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-logo">
            @if($companyProfile->logo_path && file_exists(public_path($companyProfile->logo_path)))
                <img src="{{ public_path($companyProfile->logo_path) }}" alt="{{ $companyProfile->company_name }}" class="logo">
            @elseif(file_exists(public_path('images/logo-supernova-colored-full.png')))
                <img src="{{ public_path('images/logo-supernova-colored-full.png') }}" alt="{{ $companyProfile->company_name }}" class="logo">
            @elseif(file_exists(public_path('images/logo-supernova-colored-full.svg')))
                <img src="{{ public_path('images/logo-supernova-colored-full.svg') }}" alt="{{ $companyProfile->company_name }}" class="logo">
            @endif
        </div>
        <div class="header-info">
            <div class="company-name">{{ $companyProfile->company_name }}</div>
            <div class="company-details">
                {{ $companyProfile->legal_address }}, {{ $companyProfile->legal_postal_code }} {{ $companyProfile->legal_city }} ({{ $companyProfile->legal_province }})<br>
                P.IVA: {{ $companyProfile->vat_number }} - C.F.: {{ $companyProfile->tax_code }}<br>
                @if($companyProfile->phone)Tel: {{ $companyProfile->phone }}@endif
                @if($companyProfile->phone && $companyProfile->email) - @endif
                @if($companyProfile->email)Email: {{ $companyProfile->email }}@endif
            </div>
        </div>
    </div>

    <!-- Document Title -->
    <h2 style="text-align: center; color: #333; margin-bottom: 30px;">PREVENTIVO</h2>

    <!-- Customer and Quotation Info -->
    <div class="row">
        <div class="col-6">
            <div class="info-block">
                <h3>Dati Cliente</h3>
                <div class="info-row">
                    <div class="info-label">Ragione Sociale:</div>
                    <div class="info-value"><strong>{{ $quotation->customer->company_name }}</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">P.IVA:</div>
                    <div class="info-value">{{ $quotation->customer->vat_number }}</div>
                </div>
                @if($quotation->customer->sdi_code)
                <div class="info-row">
                    <div class="info-label">Codice SDI:</div>
                    <div class="info-value">{{ $quotation->customer->sdi_code }}</div>
                </div>
                @endif
                <div class="info-row">
                    <div class="info-label">Indirizzo:</div>
                    <div class="info-value">{{ $quotation->customer->address }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Città:</div>
                    <div class="info-value">{{ $quotation->customer->postal_code }} {{ $quotation->customer->city }} ({{ $quotation->customer->province }})</div>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="info-block">
                <h3>Dati Preventivo</h3>
                <div class="info-row">
                    <div class="info-label">Numero:</div>
                    <div class="info-value"><strong>{{ $quotation->number }}</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Data:</div>
                    <div class="info-value">{{ $quotation->date->format('d/m/Y') }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Scadenza:</div>
                    <div class="info-value">{{ $quotation->valid_until->format('d/m/Y') }}</div>
                </div>
                @if($quotation->project)
                <div class="info-row">
                    <div class="info-label">Progetto:</div>
                    <div class="info-value">{{ $quotation->project->name }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Validity Notice -->
    <div class="validity-notice">
        <strong>Validità offerta:</strong> Il presente preventivo ha validità fino al {{ $quotation->valid_until->format('d/m/Y') }}
    </div>

    <!-- Items Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 50%;">Descrizione</th>
                <th class="text-center" style="width: 10%;">Quantità</th>
                <th class="text-center" style="width: 10%;">U.M.</th>
                <th class="text-right" style="width: 15%;">Prezzo Unit.</th>
                <th class="text-right" style="width: 15%;">Totale</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quotation->items as $item)
            @php
                // Determine unit of measure based on item type
                $unit = 'pz';
                if (in_array($item->item_type, ['design', 'assembly', 'housing_design'])) {
                    $unit = 'ore';
                }

                // Use custom description if available, otherwise fallback to description
                $displayDescription = $item->custom_description ?: $item->description;
            @endphp
            <tr>
                <td>
                    <strong>{{ $displayDescription }}</strong>
                    @if($item->notes)
                    <br><small>{{ $item->notes }}</small>
                    @endif
                </td>
                <td class="text-center">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                <td class="text-center">{{ $unit }}</td>
                <td class="text-right">€ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                <td class="text-right">€ {{ number_format($item->total, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totals -->
    <div class="totals">
        @if($quotation->discount_amount > 0)
        <div class="total-row">
            <span class="total-label">Subtotale:</span>
            <span class="total-value">€ {{ number_format($quotation->subtotal, 2, ',', '.') }}</span>
        </div>
        <div class="total-row">
            <span class="total-label">Sconto ({{ $quotation->discount_rate }}%):</span>
            <span class="total-value">- € {{ number_format($quotation->discount_amount, 2, ',', '.') }}</span>
        </div>
        @endif
        <div class="total-row">
            <span class="total-label">Imponibile:</span>
            <span class="total-value">€ {{ number_format($quotation->subtotal - $quotation->discount_amount, 2, ',', '.') }}</span>
        </div>
        <div class="total-row">
            <span class="total-label">IVA ({{ $quotation->tax_rate }}%):</span>
            <span class="total-value">€ {{ number_format($quotation->tax_amount, 2, ',', '.') }}</span>
        </div>
        <div class="total-row final">
            <span class="total-label">Totale:</span>
            <span class="total-value">€ {{ number_format($quotation->total, 2, ',', '.') }}</span>
        </div>
    </div>

    <!-- Payment Terms from PaymentTerm -->
    @if($quotation->customer->paymentTerm && $quotation->customer->paymentTerm->tranches->isNotEmpty())
    <div class="payment-terms">
        <h4>Condizioni di Pagamento:</h4>
        @foreach($quotation->customer->paymentTerm->tranches as $tranche)
        <div class="tranche-item">
            <strong>{{ $tranche->name }}:</strong>
            {{ $tranche->percentage }}% -
            € {{ number_format(($quotation->total * $tranche->percentage / 100), 2, ',', '.') }}
            @if($tranche->due_days > 0)
            <span style="font-size: 10px; color: #666;">({{ $tranche->due_days }} giorni {{ $tranche->due_from }})</span>
            @endif
        </div>
        @endforeach
        <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #dee2e6;">
            <strong>Modalità di pagamento:</strong> Bonifico Bancario<br>
            @if($companyProfile->iban)
            <strong>IBAN:</strong> {{ $companyProfile->iban }}<br>
            @endif
            @if($companyProfile->bic)
            <strong>BIC/SWIFT:</strong> {{ $companyProfile->bic }}
            @endif
        </div>
    </div>
    @endif

    <!-- Notes -->
    @if($quotation->notes)
    <div style="margin-top: 30px; padding: 15px; background-color: #f8f9fa; border-radius: 5px;">
        <h4 style="margin-top: 0; color: #1A4A4A;">Note</h4>
        <p style="margin: 0;">{{ $quotation->notes }}</p>
    </div>
    @endif

    <!-- Contract Clauses (if available) -->
    @if($quotation->customer->activeContract && $quotation->customer->activeContract->clauses)
    <div class="clauses-section">
        <h4>Clausole Contrattuali</h4>
        @foreach($quotation->customer->activeContract->clauses as $clause)
        <p style="margin: 5px 0;"><strong>{{ $clause->title }}:</strong> {{ $clause->content }}</p>
        @endforeach
    </div>
    @endif

    <!-- Signatures Section -->
    <div class="signatures-section">
        <div class="signature-declaration">
            <strong>Dichiarazione:</strong> Il sottoscritto firmatario dichiara di avere i poteri necessari per la sottoscrizione del presente preventivo in rappresentanza della società indicata e di accettarne integralmente i termini e le condizioni.
        </div>

        <div class="signature-row">
            <div class="signature-box" style="width: 48%; float: left;">
                <div class="signature-label">Per {{ $companyProfile->company_name }}</div>
                <div class="signature-name">
                    <strong>Alessandro Cursoli</strong><br>
                    <span class="signature-role">Amministratore Unico</span>
                </div>
            </div>
            <div class="signature-box" style="width: 48%; float: right;">
                <div class="signature-label">Per {{ $quotation->customer->company_name }}</div>
                <div class="signature-name">
                    _______________________________<br>
                    <span class="signature-role">Firma</span>
                </div>
            </div>
        </div>
        <div style="clear: both;"></div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>
            {{ $companyProfile->company_name }} - {{ $companyProfile->legal_address }}, {{ $companyProfile->legal_postal_code }} {{ $companyProfile->legal_city }} ({{ $companyProfile->legal_province }})<br>
            P.IVA: {{ $companyProfile->vat_number }}
            @if($companyProfile->phone) - Tel: {{ $companyProfile->phone }}@endif
            @if($companyProfile->email) - Email: {{ $companyProfile->email }}@endif<br>
            @if($companyProfile->iban)IBAN: {{ $companyProfile->iban }}@endif
            @if($companyProfile->bic) - BIC/SWIFT: {{ $companyProfile->bic }}@endif
        </p>
    </div>
</body>
</html>
