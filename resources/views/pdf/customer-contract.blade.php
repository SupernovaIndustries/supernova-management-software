<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contratto {{ $contract->contract_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.6;
            color: #333;
        }

        .container {
            padding: 30px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 3px solid #1e40af;
            padding-bottom: 20px;
        }

        .contract-title {
            font-size: 24pt;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 10px;
        }

        .contract-number {
            font-size: 14pt;
            color: #666;
            margin-bottom: 5px;
        }

        .contract-subtitle {
            font-size: 11pt;
            color: #666;
        }

        .parties-section {
            margin: 30px 0;
        }

        .party-box {
            border: 2px solid #2563eb;
            padding: 20px;
            margin-bottom: 20px;
            background-color: #f8fafc;
        }

        .party-title {
            font-size: 13pt;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 15px;
            text-transform: uppercase;
        }

        .party-info {
            line-height: 1.8;
        }

        .party-info strong {
            color: #1e40af;
        }

        .info-grid {
            display: table;
            width: 100%;
            margin: 20px 0;
        }

        .info-col {
            display: table-cell;
            width: 50%;
            padding: 10px;
            vertical-align: top;
        }

        .info-box {
            border: 1px solid #e5e7eb;
            padding: 15px;
            background-color: #ffffff;
        }

        .info-box-title {
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 10px;
            font-size: 11pt;
        }

        .info-row {
            padding: 5px 0;
        }

        .info-label {
            font-weight: bold;
            color: #666;
            display: inline-block;
            width: 45%;
        }

        .info-value {
            display: inline-block;
            width: 53%;
        }

        .terms-section {
            margin-top: 30px;
        }

        .section-title {
            font-size: 14pt;
            font-weight: bold;
            color: #1e40af;
            margin: 25px 0 15px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #2563eb;
        }

        .terms-content {
            text-align: justify;
            line-height: 1.8;
        }

        .terms-content p {
            margin-bottom: 12px;
        }

        .article {
            margin-bottom: 20px;
        }

        .article-title {
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 8px;
            font-size: 11pt;
        }

        .article-content {
            text-align: justify;
            padding-left: 15px;
        }

        .signatures-section {
            margin-top: 50px;
            page-break-inside: avoid;
        }

        .signature-grid {
            display: table;
            width: 100%;
        }

        .signature-col {
            display: table-cell;
            width: 50%;
            padding: 20px;
            vertical-align: top;
        }

        .signature-box {
            border: 1px solid #cbd5e1;
            padding: 30px 15px;
            text-align: center;
            min-height: 120px;
        }

        .signature-title {
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 40px;
        }

        .signature-line {
            border-top: 1px solid #333;
            margin: 0 auto;
            width: 80%;
            padding-top: 5px;
            font-size: 9pt;
            color: #666;
        }

        .footer {
            position: fixed;
            bottom: 20px;
            left: 30px;
            right: 30px;
            text-align: center;
            font-size: 8pt;
            color: #999;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }

        .page-number:after {
            content: counter(page);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 4px;
            font-size: 10pt;
            font-weight: bold;
            margin-left: 10px;
        }

        .status-badge.active {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-badge.draft {
            background-color: #f3f4f6;
            color: #374151;
        }

        .status-badge.expired {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .notes-box {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
        }

        .notes-box-title {
            font-weight: bold;
            color: #92400e;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        {{-- Header --}}
        <div class="header">
            <div class="contract-title">
                @if($contract->type === 'nda')
                    ACCORDO DI RISERVATEZZA
                @elseif($contract->type === 'service_agreement')
                    CONTRATTO DI SERVIZIO
                @elseif($contract->type === 'supply_contract')
                    CONTRATTO DI FORNITURA
                @elseif($contract->type === 'partnership')
                    ACCORDO DI PARTNERSHIP
                @else
                    CONTRATTO
                @endif
            </div>
            <div class="contract-number">
                N. {{ $contract->contract_number }}
                <span class="status-badge {{ $contract->status }}">
                    @if($contract->status === 'active')ATTIVO
                    @elseif($contract->status === 'draft')BOZZA
                    @elseif($contract->status === 'expired')SCADUTO
                    @elseif($contract->status === 'terminated')TERMINATO
                    @endif
                </span>
            </div>
            <div class="contract-subtitle">{{ $contract->title }}</div>
        </div>

        {{-- Parties --}}
        <div class="parties-section">
            {{-- Company (Parte 1) --}}
            <div class="party-box">
                <div class="party-title">Parte 1 - Fornitore</div>
                <div class="party-info">
                    <strong>{{ $company->company_name ?? 'Supernova SRL' }}</strong><br>
                    @if($company)
                        Sede legale: {{ $company->address }}, {{ $company->postal_code }} {{ $company->city }} ({{ $company->province }})<br>
                        Partita IVA: {{ $company->vat_number }}<br>
                        @if($company->tax_code)Codice Fiscale: {{ $company->tax_code }}<br>@endif
                        @if($company->pec_email)PEC: {{ $company->pec_email }}<br>@endif
                        @if($company->email)Email: {{ $company->email }}<br>@endif
                        @if($company->phone)Telefono: {{ $company->phone }}@endif
                    @else
                        Sede legale: Via Example 123, 00100 Roma (RM)<br>
                        Partita IVA: IT12345678901
                    @endif
                </div>
            </div>

            {{-- Customer (Parte 2) --}}
            <div class="party-box">
                <div class="party-title">Parte 2 - Cliente</div>
                <div class="party-info">
                    <strong>{{ $contract->customer->company_name }}</strong><br>
                    @if($contract->customer->address)
                        Sede legale: {{ $contract->customer->address }},
                        {{ $contract->customer->postal_code }} {{ $contract->customer->city }}
                        @if($contract->customer->province)({{ $contract->customer->province }})@endif<br>
                    @endif
                    @if($contract->customer->vat_number)
                        Partita IVA: {{ $contract->customer->vat_number }}<br>
                    @endif
                    @if($contract->customer->tax_code)
                        Codice Fiscale: {{ $contract->customer->tax_code }}<br>
                    @endif
                    @if($contract->customer->pec_email)
                        PEC: {{ $contract->customer->pec_email }}<br>
                    @endif
                    @if($contract->customer->email)
                        Email: {{ $contract->customer->email }}<br>
                    @endif
                    @if($contract->customer->phone)
                        Telefono: {{ $contract->customer->phone }}
                    @endif
                </div>
            </div>
        </div>

        {{-- Contract Info --}}
        <div class="info-grid">
            <div class="info-col">
                <div class="info-box">
                    <div class="info-box-title">INFORMAZIONI CONTRATTUALI</div>
                    <div class="info-row">
                        <span class="info-label">Data Inizio:</span>
                        <span class="info-value">{{ $contract->start_date->format('d/m/Y') }}</span>
                    </div>
                    @if($contract->end_date)
                    <div class="info-row">
                        <span class="info-label">Data Scadenza:</span>
                        <span class="info-value">{{ $contract->end_date->format('d/m/Y') }}</span>
                    </div>
                    @endif
                    @if($contract->signed_at)
                    <div class="info-row">
                        <span class="info-label">Data Firma:</span>
                        <span class="info-value">{{ $contract->signed_at->format('d/m/Y') }}</span>
                    </div>
                    @endif
                </div>
            </div>
            <div class="info-col">
                <div class="info-box">
                    <div class="info-box-title">VALORE ECONOMICO</div>
                    @if($contract->contract_value)
                    <div class="info-row">
                        <span class="info-label">Valore Contratto:</span>
                        <span class="info-value">
                            {{ $contract->currency ?? 'EUR' }} {{ number_format($contract->contract_value, 2) }}
                        </span>
                    </div>
                    @else
                    <div class="info-row">
                        <span class="info-value" style="width: 100%; color: #666;">
                            Valore non specificato
                        </span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Terms and Conditions --}}
        <div class="terms-section">
            <div class="section-title">TERMINI E CONDIZIONI</div>

            @if($contract->terms)
                <div class="terms-content">
                    {{-- Check if terms contain HTML (AI generated) or plain text --}}
                    @if(str_contains($contract->terms, '<h3>') || str_contains($contract->terms, '<p>'))
                        {{-- AI-generated HTML content - render as is --}}
                        {!! $contract->terms !!}
                    @else
                        {{-- Plain text - convert line breaks --}}
                        {!! nl2br(e($contract->terms)) !!}
                    @endif
                </div>
            @else
                {{-- Default terms based on contract type --}}
                @if($contract->type === 'nda')
                    <div class="article">
                        <div class="article-title">Art. 1 - Oggetto dell'Accordo</div>
                        <div class="article-content">
                            Il presente Accordo di Riservatezza ha per oggetto la regolamentazione degli obblighi di riservatezza
                            relativi alle informazioni confidenziali che le Parti si scambieranno nell'ambito della collaborazione
                            per il progetto {{ $contract->title }}.
                        </div>
                    </div>

                    <div class="article">
                        <div class="article-title">Art. 2 - Definizione di Informazioni Confidenziali</div>
                        <div class="article-content">
                            Per "Informazioni Confidenziali" si intendono tutte le informazioni, dati, documenti, specifiche tecniche,
                            know-how, progetti, processi e qualsiasi altra informazione di natura tecnica, commerciale o industriale
                            comunicata da una Parte all'altra, sia in forma scritta che orale.
                        </div>
                    </div>

                    <div class="article">
                        <div class="article-title">Art. 3 - Obblighi di Riservatezza</div>
                        <div class="article-content">
                            Le Parti si impegnano a:
                            <br>a) Mantenere strettamente riservate tutte le Informazioni Confidenziali;
                            <br>b) Non divulgare a terzi le Informazioni Confidenziali senza previo consenso scritto;
                            <br>c) Utilizzare le Informazioni Confidenziali esclusivamente per gli scopi del presente Accordo.
                        </div>
                    </div>

                @elseif($contract->type === 'service_agreement')
                    <div class="article">
                        <div class="article-title">Art. 1 - Oggetto del Contratto</div>
                        <div class="article-content">
                            Il presente Contratto ha per oggetto la fornitura di servizi di progettazione, sviluppo e assistenza
                            tecnica per il progetto {{ $contract->title }}.
                        </div>
                    </div>

                    <div class="article">
                        <div class="article-title">Art. 2 - Obblighi del Fornitore</div>
                        <div class="article-content">
                            Il Fornitore si impegna a svolgere i servizi oggetto del presente Contratto con la massima
                            professionalità e competenza, nel rispetto delle tempistiche concordate e degli standard qualitativi
                            richiesti dal Cliente.
                        </div>
                    </div>

                    <div class="article">
                        <div class="article-title">Art. 3 - Corrispettivo e Modalità di Pagamento</div>
                        <div class="article-content">
                            @if($contract->contract_value)
                            Il corrispettivo per i servizi è fissato in € {{ number_format($contract->contract_value, 2) }}.
                            I pagamenti saranno effettuati secondo le modalità e le scadenze concordate tra le Parti.
                            @else
                            Il corrispettivo e le modalità di pagamento saranno concordati separatamente per ciascun servizio.
                            @endif
                        </div>
                    </div>

                @elseif($contract->type === 'supply_contract')
                    <div class="article">
                        <div class="article-title">Art. 1 - Oggetto della Fornitura</div>
                        <div class="article-content">
                            Il presente Contratto ha per oggetto la fornitura di componenti elettronici, materiali e servizi
                            correlati per il progetto {{ $contract->title }}.
                        </div>
                    </div>

                    <div class="article">
                        <div class="article-title">Art. 2 - Caratteristiche della Fornitura</div>
                        <div class="article-content">
                            La fornitura sarà effettuata secondo le specifiche tecniche concordate, nel rispetto degli standard
                            di qualità e delle normative vigenti in materia.
                        </div>
                    </div>

                @else
                    <div class="article">
                        <div class="article-title">Art. 1 - Oggetto del Contratto</div>
                        <div class="article-content">
                            Il presente Contratto regola i rapporti tra le Parti per {{ $contract->title }}.
                        </div>
                    </div>
                @endif

                {{-- Common clauses for all contract types --}}
                <div class="article">
                    <div class="article-title">Art. {{ $contract->type === 'nda' ? '4' : ($contract->type === 'service_agreement' ? '4' : '3') }} - Durata</div>
                    <div class="article-content">
                        Il presente Contratto avrà durata dal {{ $contract->start_date->format('d/m/Y') }}
                        @if($contract->end_date)
                            al {{ $contract->end_date->format('d/m/Y') }}.
                        @else
                            fino a revoca scritta da parte di una delle Parti.
                        @endif
                    </div>
                </div>

                <div class="article">
                    <div class="article-title">Art. {{ $contract->type === 'nda' ? '5' : ($contract->type === 'service_agreement' ? '5' : '4') }} - Legge Applicabile e Foro Competente</div>
                    <div class="article-content">
                        Il presente Contratto è regolato dalla legge italiana. Per qualsiasi controversia derivante dal presente
                        Contratto sarà competente in via esclusiva il Foro di {{ $company->city ?? 'Roma' }}.
                    </div>
                </div>
            @endif
        </div>

        {{-- Notes --}}
        @if($contract->notes)
        <div class="notes-box">
            <div class="notes-box-title">Note Aggiuntive:</div>
            <div>{!! nl2br(e($contract->notes)) !!}</div>
        </div>
        @endif

        {{-- Signatures --}}
        <div class="signatures-section">
            <div class="section-title">FIRME</div>
            <div style="margin-bottom: 15px; text-align: center; color: #666; font-size: 9pt;">
                Luogo: {{ $company->city ?? 'Roma' }}, Data: {{ $contract->signed_at ? $contract->signed_at->format('d/m/Y') : '___/___/______' }}
            </div>

            <div class="signature-grid">
                <div class="signature-col">
                    <div class="signature-box">
                        <div class="signature-title">Per {{ $company->company_name ?? 'Supernova SRL' }}</div>
                        <div class="signature-line">Firma e Timbro</div>
                    </div>
                </div>
                <div class="signature-col">
                    <div class="signature-box">
                        <div class="signature-title">Per {{ $contract->customer->company_name }}</div>
                        <div class="signature-line">Firma e Timbro</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="footer">
            Contratto N. {{ $contract->contract_number }} • Generato il {{ now()->format('d/m/Y H:i') }}
            • Pagina <span class="page-number"></span>
        </div>
    </div>
</body>
</html>
