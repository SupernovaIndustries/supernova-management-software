<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DDT {{ $log->ddt_number }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 15px;
        }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 20px;
            border-bottom: 3px solid #1A4A4A;
            padding-bottom: 15px;
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
        .document-title {
            background-color: #1A4A4A;
            color: white;
            padding: 10px;
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .row {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        .col-6 {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 10px;
        }
        .col-6:last-child {
            padding-right: 0;
            padding-left: 10px;
        }
        .info-block {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
            min-height: 80px;
        }
        .info-block h3 {
            margin: 0 0 8px 0;
            color: #1A4A4A;
            font-size: 12px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 4px;
        }
        .info-row {
            margin-bottom: 3px;
            font-size: 10px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 110px;
        }
        .goods-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background-color: white;
        }
        .goods-table thead {
            background-color: #1A4A4A;
            color: white;
        }
        .goods-table th {
            padding: 8px;
            text-align: left;
            font-weight: bold;
            font-size: 10px;
        }
        .goods-table td {
            padding: 8px;
            border-bottom: 1px solid #dee2e6;
            font-size: 10px;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .transport-section {
            background-color: #e9ecef;
            padding: 12px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .transport-section h4 {
            margin: 0 0 8px 0;
            color: #1A4A4A;
            font-size: 11px;
        }
        .transport-row {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }
        .transport-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            font-size: 10px;
        }
        .checkbox {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1px solid #333;
            margin-right: 5px;
            vertical-align: middle;
        }
        .checkbox.checked::after {
            content: 'X';
            display: block;
            text-align: center;
            line-height: 12px;
            font-weight: bold;
        }
        .signature-section {
            margin-top: 20px;
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 4px;
            page-break-inside: avoid;
        }
        .signature-section h4 {
            margin: 0 0 10px 0;
            text-align: center;
            color: #1A4A4A;
            font-size: 11px;
        }
        .signature-row {
            display: table;
            width: 100%;
            margin-top: 20px;
        }
        .signature-col {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: bottom;
            padding: 0 10px;
        }
        .signature-box {
            border: 1px solid #333;
            height: 60px;
            margin: 10px 0;
            background-color: #fff;
        }
        .signature-label {
            font-size: 9px;
            font-weight: bold;
            margin-top: 5px;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            font-size: 8px;
            color: #666;
        }
        @page {
            margin: 15mm;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-logo">
            @if($company->logo_path && file_exists(public_path($company->logo_path)))
                <img src="{{ public_path($company->logo_path) }}" alt="{{ $company->company_name }}" class="logo">
            @elseif(file_exists(public_path('images/logo-supernova-colored-full.png')))
                <img src="{{ public_path('images/logo-supernova-colored-full.png') }}" alt="Supernova Industries" class="logo">
            @elseif(file_exists(public_path('images/logo-supernova-colored-full.svg')))
                <img src="{{ public_path('images/logo-supernova-colored-full.svg') }}" alt="Supernova Industries" class="logo">
            @endif
        </div>
        <div class="header-info">
            <div class="company-name">{{ $company->company_name ?? 'SUPERNOVA INDUSTRIES S.R.L.' }}</div>
            <div class="company-details">
                {{ $company->address ?? 'Via Example, 123' }} - {{ $company->postal_code ?? '20100' }} {{ $company->city ?? 'Milano' }} ({{ $company->province ?? 'MI' }})<br>
                P.IVA: {{ $company->vat_number ?? '12345678901' }} - C.F.: {{ $company->tax_code ?? '12345678901' }}<br>
                @if($company->administrator_name)
                Amministratore: {{ $company->administrator_name }}<br>
                @endif
                Tel: {{ $company->phone ?? '+39 02 1234567' }} - Email: {{ $company->email ?? 'info@supernovaindustries.it' }}
            </div>
        </div>
    </div>

    <!-- Document Title -->
    <div class="document-title">
        DOCUMENTO DI TRASPORTO - DDT N. {{ $log->ddt_number }}
    </div>

    <!-- Main Info Row -->
    <div class="row">
        <!-- Destinatario -->
        <div class="col-6">
            <div class="info-block">
                <h3>DESTINATARIO / CESSIONARIO</h3>
                <div class="info-row">
                    <strong>{{ $customer->company_name ?? $customer->name }}</strong>
                </div>
                @if($customer->vat_number)
                <div class="info-row">
                    P.IVA: {{ $customer->vat_number }}
                </div>
                @endif
                @if($customer->tax_code)
                <div class="info-row">
                    C.F.: {{ $customer->tax_code }}
                </div>
                @endif
                @if($customer->sdi_code)
                <div class="info-row">
                    Codice SDI: {{ $customer->sdi_code }}
                </div>
                @endif
            </div>

            <!-- Luogo di Destinazione -->
            <div class="info-block">
                <h3>LUOGO DI DESTINAZIONE</h3>
                @if($log->ddt_delivery_address)
                    <div class="info-row">
                        {{ $log->ddt_delivery_address['address'] ?? $customer->address }}
                    </div>
                    <div class="info-row">
                        {{ $log->ddt_delivery_address['postal_code'] ?? $customer->postal_code }}
                        {{ $log->ddt_delivery_address['city'] ?? $customer->city }}
                        @if(isset($log->ddt_delivery_address['province']))
                        ({{ $log->ddt_delivery_address['province'] }})
                        @elseif($customer->province)
                        ({{ $customer->province }})
                        @endif
                    </div>
                    @if(isset($log->ddt_delivery_address['country']))
                    <div class="info-row">
                        {{ $log->ddt_delivery_address['country'] }}
                    </div>
                    @endif
                @else
                    <div class="info-row">
                        {{ $customer->address }}
                    </div>
                    <div class="info-row">
                        {{ $customer->postal_code }} {{ $customer->city }}
                        @if($customer->province)
                        ({{ $customer->province }})
                        @endif
                    </div>
                    @if($customer->country)
                    <div class="info-row">
                        {{ $customer->country }}
                    </div>
                    @endif
                @endif
            </div>
        </div>

        <!-- Dati Documento -->
        <div class="col-6">
            <div class="info-block">
                <h3>DATI DOCUMENTO</h3>
                <div class="info-row">
                    <span class="info-label">Numero DDT:</span>
                    <strong style="font-size: 13px;">{{ $log->ddt_number }}</strong>
                </div>
                <div class="info-row">
                    <span class="info-label">Data:</span>
                    {{ $log->ddt_date->format('d/m/Y') }}
                </div>
                <div class="info-row">
                    <span class="info-label">Rif. Progetto:</span>
                    {{ $project->code ?? 'N/A' }}
                </div>
                @if($project->name)
                <div class="info-row">
                    <span class="info-label">Nome Progetto:</span>
                    {{ $project->name }}
                </div>
                @endif
            </div>

            <!-- Ordine Info -->
            <div class="info-block">
                <h3>INFORMAZIONI ORDINE</h3>
                @php
                    $acceptedQuotation = $project->quotations()->where('status', 'accepted')->first();
                @endphp
                @if($acceptedQuotation)
                <div class="info-row">
                    <span class="info-label">N. Ordine:</span>
                    {{ $acceptedQuotation->number }}
                </div>
                <div class="info-row">
                    <span class="info-label">Data Ordine:</span>
                    {{ $acceptedQuotation->date->format('d/m/Y') }}
                </div>
                @endif
                <div class="info-row">
                    <span class="info-label">Cond. Pagamento:</span>
                    <strong>{{ $log->ddt_payment_condition === 'in_conto' ? 'IN CONTO' : 'IN SALDO' }}</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Transport Type -->
    <div class="transport-section">
        <div class="transport-row">
            <div class="transport-col">
                <strong>TRASPORTO A CURA:</strong><br>
                <span class="checkbox {{ $log->ddt_transport_type === 'cedente' ? 'checked' : '' }}"></span> Cedente (Mittente)<br>
                <span class="checkbox {{ $log->ddt_transport_type === 'cessionario' ? 'checked' : '' }}"></span> Cessionario (Destinatario)
            </div>
            <div class="transport-col">
                <strong>DATA CONSEGNA:</strong><br>
                {{ $log->ddt_date->format('d/m/Y') }}
            </div>
        </div>
    </div>

    <!-- Causale del Trasporto -->
    <div style="background-color: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0;">
        <strong style="color: #856404;">CAUSALE DEL TRASPORTO:</strong><br>
        <span style="font-size: 10px;">{{ $log->ddt_reason }}</span>
    </div>

    <!-- Goods Table -->
    <table class="goods-table">
        <thead>
            <tr>
                <th style="width: 15%;">Quantità</th>
                <th style="width: 85%;">Descrizione Beni</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-center">
                    <strong>{{ $log->boards_count }}</strong><br>
                    <span style="font-size: 9px;">{{ $log->boards_count == 1 ? 'pezzo' : 'pezzi' }}</span>
                </td>
                <td>
                    <strong>{{ $log->ddt_goods_description ?? 'Schede elettroniche assemblate' }}</strong><br>
                    <span style="font-size: 9px; color: #666;">
                        Lotto: {{ $log->batch_number }}<br>
                        Tipo: {{ $log->is_prototype ? 'PROTOTIPO/TEST' : 'PRODUZIONE' }}<br>
                        Data Assemblaggio: {{ $log->assembly_date->format('d/m/Y') }}
                    </span>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Aspetto e Numero Colli -->
    <div class="row">
        <div class="col-6">
            <div style="background-color: #f8f9fa; padding: 10px; border-radius: 4px;">
                <strong style="font-size: 10px;">ASPETTO ESTERIORE:</strong><br>
                <span style="font-size: 10px;">{{ $log->ddt_appearance ?? 'Scatola' }}</span>
            </div>
        </div>
        <div class="col-6">
            <div style="background-color: #f8f9fa; padding: 10px; border-radius: 4px;">
                <strong style="font-size: 10px;">N. COLLI:</strong> <span style="font-size: 10px;">{{ $log->ddt_packages_count }}</span><br>
                @if($log->ddt_weight_kg)
                <strong style="font-size: 10px;">PESO:</strong> <span style="font-size: 10px;">{{ number_format($log->ddt_weight_kg, 2, ',', '.') }} kg</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Signature Section -->
    <div class="signature-section">
        <h4>FIRME PER RICEVUTA MERCE</h4>
        <p style="text-align: center; font-size: 9px; color: #666; margin: 5px 0;">
            Con la firma del presente documento si attesta la ricezione e la conformità della merce consegnata
        </p>

        <div class="signature-row">
            <div class="signature-col">
                <div style="font-size: 9px; margin-bottom: 5px;">
                    Data: ___ / ___ / _______
                </div>
                <div class="signature-box">
                    @if($log->ddt_conductor_signature)
                    <img src="{{ $log->ddt_conductor_signature }}" style="max-width: 100%; max-height: 100%;" alt="Firma Conducente">
                    @endif
                </div>
                <div class="signature-label">FIRMA DEL CONDUCENTE</div>
            </div>
            <div class="signature-col">
                <div style="font-size: 9px; margin-bottom: 5px;">
                    Ora: ___ : ___
                </div>
                <div class="signature-box">
                    @if($log->ddt_recipient_signature)
                    <img src="{{ $log->ddt_recipient_signature }}" style="max-width: 100%; max-height: 100%;" alt="Firma Destinatario">
                    @endif
                </div>
                <div class="signature-label">FIRMA DEL DESTINATARIO</div>
            </div>
        </div>

        <div style="margin-top: 15px; padding: 8px; background-color: #f8f9fa; border-radius: 4px;">
            <strong style="font-size: 9px;">Annotazioni del destinatario:</strong>
            <div style="height: 40px; border-bottom: 1px solid #333; margin-top: 5px;"></div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>
            {{ $company->company_name ?? 'SUPERNOVA INDUSTRIES S.R.L.' }} - {{ $company->address ?? 'Via Example, 123' }} - {{ $company->postal_code ?? '20100' }} {{ $company->city ?? 'Milano' }} ({{ $company->province ?? 'MI' }})<br>
            P.IVA: {{ $company->vat_number ?? '12345678901' }} - Tel: {{ $company->phone ?? '+39 02 1234567' }} - Email: {{ $company->email ?? 'info@supernovaindustries.it' }}<br>
            Documento emesso ai sensi del DPR 472/96 e successive modifiche
        </p>
    </div>
</body>
</html>
