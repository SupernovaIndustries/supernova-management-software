<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schede Assemblate</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .email-container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            border-bottom: 3px solid #3b82f6;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #3b82f6;
            margin: 0;
            font-size: 24px;
        }
        .assembly-highlight {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .assembly-highlight h2 {
            color: #1e40af;
            margin: 0 0 15px 0;
            font-size: 18px;
        }
        .assembly-count {
            font-size: 48px;
            font-weight: bold;
            color: #3b82f6;
            text-align: center;
            margin: 15px 0;
        }
        .project-info {
            margin: 25px 0;
        }
        .info-row {
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-label {
            font-weight: bold;
            color: #4b5563;
            display: inline-block;
            width: 180px;
        }
        .info-value {
            color: #1f2937;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
        }
        .badge-assembled {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .badge-tested {
            background-color: #dcfce7;
            color: #166534;
        }
        .badge-failed {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .badge-rework {
            background-color: #fef3c7;
            color: #92400e;
        }
        .progress-section {
            background-color: #f9fafb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .progress-bar-container {
            background-color: #e5e7eb;
            border-radius: 10px;
            height: 30px;
            margin: 15px 0;
            overflow: hidden;
            position: relative;
        }
        .progress-bar {
            background: linear-gradient(90deg, #3b82f6 0%, #2563eb 100%);
            height: 100%;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
            transition: width 0.3s ease;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        .stat-box {
            text-align: center;
            padding: 15px;
            background-color: #f9fafb;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #3b82f6;
            margin: 0;
        }
        .stat-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            margin-top: 5px;
        }
        .notes-section {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
        }
        .signature {
            margin-top: 20px;
        }
        .company-name {
            font-weight: bold;
            color: #3b82f6;
        }
        .icon {
            font-size: 48px;
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>Nuove Schede Assemblate</h1>
        </div>

        <div class="icon">🔧</div>

        <p>Spettabile <strong>{{ $project->customer->company_name ?? $customerName }}</strong>,</p>

        <p>La informiamo che sono state completate nuove operazioni di assemblaggio per il progetto.</p>

        <div class="project-info">
            <div class="info-row">
                <span class="info-label">Codice Progetto:</span>
                <span class="info-value">{{ $project->code }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Nome Progetto:</span>
                <span class="info-value">{{ $project->name }}</span>
            </div>
        </div>

        <div class="assembly-highlight">
            <h2>📋 Dettagli Assemblaggio</h2>
            @if($assemblyLog->is_prototype)
            <div style="text-align: center; margin: 10px 0;">
                <span class="badge" style="background-color: #fef3c7; color: #92400e; font-size: 14px;">
                    🧪 Test/Prototipo
                </span>
            </div>
            @else
            <div style="text-align: center; margin: 10px 0;">
                <span class="badge" style="background-color: #dcfce7; color: #166534; font-size: 14px;">
                    ⚙️ Produzione
                </span>
            </div>
            @endif
            <div class="assembly-count">
                {{ $assemblyLog->boards_count }} {{ $assemblyLog->boards_count == 1 ? 'scheda' : 'schede' }}
            </div>
            <div style="text-align: center; margin: 15px 0;">
                @if($assemblyLog->status === 'assembled')
                <span class="badge badge-assembled">Assemblato</span>
                @elseif($assemblyLog->status === 'tested')
                <span class="badge badge-tested">Testato (OK)</span>
                @elseif($assemblyLog->status === 'failed')
                <span class="badge badge-failed">Test Fallito</span>
                @elseif($assemblyLog->status === 'rework')
                <span class="badge badge-rework">Rework Necessario</span>
                @endif
            </div>
            <div style="margin-top: 15px;">
                <div class="info-row">
                    <span class="info-label">Data Assemblaggio:</span>
                    <span class="info-value">{{ $assemblyDate }}</span>
                </div>
                @if($assemblyLog->batch_number)
                <div class="info-row">
                    <span class="info-label">Numero Lotto:</span>
                    <span class="info-value">{{ $assemblyLog->batch_number }}</span>
                </div>
                @endif
                @if($assemblyLog->user)
                <div class="info-row">
                    <span class="info-label">Operatore:</span>
                    <span class="info-value">{{ $assemblyLog->user->name }}</span>
                </div>
                @endif
            </div>
        </div>

        @if($assemblyLog->notes)
        <div class="notes-section">
            <p style="margin: 0 0 5px 0; font-weight: bold; color: #92400e;">📝 Note e Osservazioni</p>
            <p style="margin: 0; color: #78350f;">{{ $assemblyLog->notes }}</p>
        </div>
        @endif

        <div class="progress-section">
            <h3 style="color: #4b5563; margin-top: 0; font-size: 16px;">📊 Avanzamento Produzione</h3>

            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-number">{{ $totalOrdered }}</div>
                    <div class="stat-label">Totale Ordinate</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">{{ $totalAssembled }}</div>
                    <div class="stat-label">Assemblate</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">{{ $remaining }}</div>
                    <div class="stat-label">Rimanenti</div>
                </div>
            </div>

            @php
                $percentage = $totalOrdered > 0 ? round(($totalAssembled / $totalOrdered) * 100) : 0;
            @endphp

            <div class="progress-bar-container">
                <div class="progress-bar" style="width: {{ $percentage }}%">
                    {{ $percentage }}%
                </div>
            </div>

            @if($remaining > 0)
            <p style="color: #6b7280; font-size: 13px; margin: 10px 0 0 0; text-align: center;">
                Il nostro team continua a lavorare per completare le rimanenti {{ $remaining }} {{ $remaining == 1 ? 'scheda' : 'schede' }}.
            </p>
            @else
            <p style="color: #166534; font-size: 14px; margin: 10px 0 0 0; text-align: center; font-weight: bold;">
                ✅ Tutte le schede sono state assemblate!
            </p>
            @endif
        </div>

        @if($assemblyLog->qcDocuments && $assemblyLog->qcDocuments->count() > 0)
        <div style="background-color: #ecfdf5; border-radius: 8px; padding: 20px; margin: 20px 0; border: 1px solid #d1fae5;">
            <h3 style="color: #065f46; margin-top: 0; font-size: 16px;">✅ Controllo Qualità</h3>
            <p style="color: #047857; margin: 0; font-size: 14px;">
                Sono stati caricati <strong>{{ $assemblyLog->qcDocuments->count() }}</strong> documenti QC per questa sessione di assemblaggio.
                La documentazione è disponibile nel sistema di gestione del progetto.
            </p>
        </div>
        @endif

        <div style="background-color: #f9fafb; border-radius: 8px; padding: 20px; margin: 30px 0; border: 1px solid #e5e7eb;">
            <p style="margin: 0 0 10px 0; font-weight: bold; color: #4b5563;">
                📧 Contatti
            </p>
            <p style="margin: 0 0 15px 0; color: #6b7280; font-size: 14px;">
                Per qualsiasi domanda sull'avanzamento della produzione, non esiti a contattarci:
            </p>
            <div style="margin-left: 10px;">
                <p style="margin: 5px 0;">
                    <a href="mailto:alessandro.cursoli@supernovaindustries.it" style="color: #3b82f6; text-decoration: none; font-weight: bold;">
                        ✉️ alessandro.cursoli@supernovaindustries.it
                    </a>
                </p>
                <p style="margin: 5px 0;">
                    <a href="mailto:info@supernovaindustries.it" style="color: #3b82f6; text-decoration: none; font-weight: bold;">
                        ✉️ info@supernovaindustries.it
                    </a>
                </p>
            </div>
        </div>

        <div class="footer">
            <div class="signature">
                <p style="margin: 5px 0;">Cordiali saluti,</p>
                <p style="margin: 5px 0;"><strong>Alessandro Cursoli</strong></p>
                <p style="margin: 5px 0; font-style: italic;">Amministratore Unico</p>
                <p style="margin: 15px 0 5px 0;" class="company-name">Supernova Industries S.R.L.</p>
                <p style="margin: 5px 0;">P.IVA 08959350722</p>
                <p style="margin: 5px 0;">Viale Papa Giovanni XXIII 193, Bari 70124</p>
            </div>
        </div>
    </div>
</body>
</html>
