<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DDT {{ $ddt->number }}</title>
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
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #dc3545;
            padding-bottom: 20px;
        }
        .logo-placeholder {
            width: 150px;
            height: 60px;
            background-color: #f0f0f0;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #ddd;
        }
        .company-info {
            margin-top: 10px;
            font-size: 10px;
            color: #666;
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
            color: #dc3545;
            font-size: 14px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 5px;
        }
        .info-row {
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table thead {
            background-color: #dc3545;
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
        table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .transport-section {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-top: 30px;
        }
        .transport-section h4 {
            margin-top: 0;
            color: #dc3545;
        }
        .signature-section {
            margin-top: 50px;
            border: 1px solid #dee2e6;
            padding: 20px;
            border-radius: 5px;
        }
        .signature-row {
            display: table;
            width: 100%;
            margin-top: 30px;
        }
        .signature-col {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: bottom;
        }
        .signature-line {
            border-bottom: 1px solid #333;
            width: 80%;
            margin: 0 auto;
            margin-bottom: 5px;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        .document-title {
            background-color: #dc3545;
            color: white;
            padding: 10px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 30px;
        }
        .note-box {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        @page {
            margin: 20mm;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo-placeholder">
            <span>LOGO</span>
        </div>
        <h1 style="margin: 0; color: #dc3545;">SUPERNOVA MANAGEMENT</h1>
        <div class="company-info">
            Via Example, 123 - 20100 Milano (MI)<br>
            P.IVA: 12345678901 - C.F.: 12345678901<br>
            Tel: +39 02 1234567 - Email: info@supernovamanagement.it
        </div>
    </div>

    <!-- Document Title -->
    <div class="document-title">
        DOCUMENTO DI TRASPORTO - DDT
    </div>

    <!-- Customer and DDT Info -->
    <div class="row">
        <div class="col-6">
            <div class="info-block">
                <h3>Destinatario</h3>
                <div class="info-row">
                    <strong>{{ $ddt->customer->business_name }}</strong>
                </div>
                <div class="info-row">
                    P.IVA: {{ $ddt->customer->vat_number }}
                </div>
                @if($ddt->customer->sdi_code)
                <div class="info-row">
                    Codice SDI: {{ $ddt->customer->sdi_code }}
                </div>
                @endif
            </div>

            <div class="info-block">
                <h3>Luogo di Consegna</h3>
                <div class="info-row">
                    {{ $ddt->shipping_address ?? $ddt->customer->address }}
                </div>
                <div class="info-row">
                    {{ $ddt->shipping_postal_code ?? $ddt->customer->postal_code }} 
                    {{ $ddt->shipping_city ?? $ddt->customer->city }} 
                    ({{ $ddt->shipping_province ?? $ddt->customer->province }})
                </div>
                @if($ddt->shipping_notes)
                <div class="info-row" style="margin-top: 10px;">
                    <strong>Note consegna:</strong> {{ $ddt->shipping_notes }}
                </div>
                @endif
            </div>
        </div>
        <div class="col-6">
            <div class="info-block">
                <h3>Dati Documento</h3>
                <div class="info-row">
                    <span class="info-label">Numero DDT:</span>
                    <strong style="font-size: 16px;">{{ $ddt->number }}</strong>
                </div>
                <div class="info-row">
                    <span class="info-label">Data:</span>
                    {{ $ddt->date->format('d/m/Y') }}
                </div>
                @if($ddt->project)
                <div class="info-row">
                    <span class="info-label">Rif. Progetto:</span>
                    {{ $ddt->project->name }}
                </div>
                @endif
                @if($ddt->reference_document)
                <div class="info-row">
                    <span class="info-label">Rif. Documento:</span>
                    {{ $ddt->reference_document }}
                </div>
                @endif
            </div>

            @if($ddt->transport_reason || $ddt->goods_appearance || $ddt->packages_number)
            <div class="info-block">
                <h3>Aspetto dei Beni</h3>
                @if($ddt->transport_reason)
                <div class="info-row">
                    <span class="info-label">Causale Trasporto:</span>
                    {{ $ddt->transport_reason }}
                </div>
                @endif
                @if($ddt->goods_appearance)
                <div class="info-row">
                    <span class="info-label">Aspetto Esteriore:</span>
                    {{ $ddt->goods_appearance }}
                </div>
                @endif
                @if($ddt->packages_number)
                <div class="info-row">
                    <span class="info-label">Numero Colli:</span>
                    {{ $ddt->packages_number }}
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>

    <!-- Items Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 10%;">Codice</th>
                <th style="width: 50%;">Descrizione</th>
                <th class="text-center" style="width: 15%;">Quantità</th>
                <th class="text-center" style="width: 10%;">U.M.</th>
                <th class="text-center" style="width: 15%;">Note</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ddt->items as $item)
            <tr>
                <td>{{ $item->code ?? '-' }}</td>
                <td>
                    <strong>{{ $item->description }}</strong>
                    @if($item->details)
                    <br><small>{{ $item->details }}</small>
                    @endif
                </td>
                <td class="text-center">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                <td class="text-center">{{ $item->unit ?? 'pz' }}</td>
                <td class="text-center">{{ $item->notes ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Transport Details -->
    <div class="transport-section">
        <h4>Dati di Trasporto</h4>
        <div class="row">
            <div class="col-6">
                <div class="info-row">
                    <span class="info-label">Trasporto a cura:</span>
                    {{ $ddt->transport_carrier ?? 'Mittente' }}
                </div>
                @if($ddt->transport_carrier_name)
                <div class="info-row">
                    <span class="info-label">Vettore:</span>
                    {{ $ddt->transport_carrier_name }}
                </div>
                @endif
                @if($ddt->transport_date)
                <div class="info-row">
                    <span class="info-label">Data Trasporto:</span>
                    {{ $ddt->transport_date->format('d/m/Y') }}
                </div>
                @endif
            </div>
            <div class="col-6">
                @if($ddt->transport_time)
                <div class="info-row">
                    <span class="info-label">Ora Trasporto:</span>
                    {{ $ddt->transport_time }}
                </div>
                @endif
                @if($ddt->gross_weight)
                <div class="info-row">
                    <span class="info-label">Peso Lordo:</span>
                    {{ $ddt->gross_weight }} kg
                </div>
                @endif
                @if($ddt->net_weight)
                <div class="info-row">
                    <span class="info-label">Peso Netto:</span>
                    {{ $ddt->net_weight }} kg
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Notes -->
    @if($ddt->notes)
    <div class="note-box">
        <strong>Note:</strong> {{ $ddt->notes }}
    </div>
    @endif

    <!-- Signature Section -->
    <div class="signature-section">
        <h4 style="text-align: center; margin-top: 0; color: #dc3545;">FIRME PER RICEVUTA MERCE</h4>
        <p style="text-align: center; font-size: 11px; color: #666;">
            Con la firma del presente documento si attesta la corrispondenza della merce consegnata con quanto indicato
        </p>
        
        <div class="signature-row">
            <div class="signature-col">
                <div style="margin-bottom: 50px;">Data: ____________________</div>
                <div class="signature-line"></div>
                <div>Firma del Vettore</div>
            </div>
            <div class="signature-col">
                <div style="margin-bottom: 50px;">Ora: ____________________</div>
                <div class="signature-line"></div>
                <div>Firma del Destinatario</div>
            </div>
        </div>

        <div style="margin-top: 30px; padding: 10px; background-color: #f8f9fa; border-radius: 5px;">
            <strong>Annotazioni del destinatario:</strong>
            <div style="height: 60px; border-bottom: 1px solid #333; margin-top: 10px;"></div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>
            SUPERNOVA MANAGEMENT - Via Example, 123 - 20100 Milano (MI)<br>
            P.IVA: 12345678901 - Tel: +39 02 1234567 - Email: info@supernovamanagement.it<br>
            Documento emesso ai sensi del DPR 472/96 e successive modifiche
        </p>
    </div>
</body>
</html>