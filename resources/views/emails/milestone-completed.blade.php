<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Milestone Completata</title>
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
            border-bottom: 3px solid #10b981;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #10b981;
            margin: 0;
            font-size: 24px;
        }
        .milestone-completed {
            background-color: #ecfdf5;
            border-left: 4px solid #10b981;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .milestone-completed h2 {
            color: #065f46;
            margin: 0 0 10px 0;
            font-size: 18px;
        }
        .milestone-next {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .milestone-next h2 {
            color: #1e40af;
            margin: 0 0 10px 0;
            font-size: 18px;
        }
        .progress-bar-container {
            background-color: #e5e7eb;
            border-radius: 10px;
            height: 30px;
            margin: 20px 0;
            overflow: hidden;
            position: relative;
        }
        .progress-bar {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
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
        .badge-success {
            background-color: #dcfce7;
            color: #166534;
        }
        .badge-info {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .milestone-description {
            color: #6b7280;
            font-style: italic;
            margin: 10px 0;
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
            color: #10b981;
        }
        .celebration {
            text-align: center;
            font-size: 48px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>Milestone Completata!</h1>
        </div>

        <div class="celebration">🎉</div>

        <p>Spettabile <strong>{{ $project->customer->company_name ?? $customerName }}</strong>,</p>

        <p>Siamo lieti di informarLa che abbiamo completato una milestone importante del progetto.</p>

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

        <div class="milestone-completed">
            <h2>✅ Milestone Completata</h2>
            <div style="margin-top: 10px;">
                <strong style="font-size: 16px; color: #065f46;">{{ $completedMilestone->name }}</strong>
                <span class="badge badge-success" style="margin-left: 10px;">
                    {{ $completedMilestone->category }}
                </span>
            </div>
            @if($completedMilestone->description)
            <p class="milestone-description">{{ $completedMilestone->description }}</p>
            @endif
            <div style="margin-top: 10px;">
                <span class="info-label">Data Completamento:</span>
                <span class="info-value">{{ $completionDate }}</span>
            </div>
        </div>

        @if(!empty($documents) && count($documents) > 0)
        <div style="background-color: #f9fafb; border-radius: 8px; padding: 20px; margin: 20px 0; border: 1px solid #e5e7eb;">
            <h3 style="color: #4b5563; margin-top: 0; font-size: 16px;">📎 Documenti Allegati</h3>
            <p style="color: #6b7280; font-size: 14px; margin-bottom: 15px;">
                I seguenti documenti sono stati caricati al completamento di questa milestone:
            </p>
            @foreach($documents as $document)
            <div style="background-color: white; padding: 12px; margin-bottom: 8px; border-radius: 6px; border-left: 3px solid #10b981;">
                <div style="display: flex; align-items: center;">
                    <span style="font-size: 20px; margin-right: 10px;">📄</span>
                    <div style="flex: 1;">
                        <strong style="color: #1f2937; font-size: 14px;">{{ $document->name }}</strong>
                        @if($document->original_filename)
                        <div style="color: #6b7280; font-size: 12px; margin-top: 2px;">
                            File: {{ $document->original_filename }}
                        </div>
                        @endif
                        @if($document->file_size)
                        <div style="color: #9ca3af; font-size: 11px; margin-top: 2px;">
                            Dimensione: {{ $document->formatted_file_size }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
            <p style="color: #6b7280; font-size: 12px; margin-top: 15px; margin-bottom: 0; font-style: italic;">
                I documenti sono stati caricati sul sistema e saranno disponibili nella documentazione del progetto.
            </p>
        </div>
        @endif

        <div style="margin: 30px 0;">
            <p style="font-weight: bold; color: #4b5563; margin-bottom: 10px;">
                Progresso Complessivo del Progetto:
            </p>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: {{ $completionPercentage }}%">
                    {{ number_format($completionPercentage, 1) }}%
                </div>
            </div>
        </div>

        @if($nextMilestone)
        <div class="milestone-next">
            <h2>🎯 Prossima Milestone in Corso</h2>
            <div style="margin-top: 10px;">
                <strong style="font-size: 16px; color: #1e40af;">{{ $nextMilestone->name }}</strong>
                <span class="badge badge-info" style="margin-left: 10px;">
                    {{ $nextMilestone->category }}
                </span>
            </div>
            @if($nextMilestone->description)
            <p class="milestone-description">{{ $nextMilestone->description }}</p>
            @endif
            @if($nextMilestone->pivot && $nextMilestone->pivot->target_date)
            <div style="margin-top: 10px;">
                <span class="info-label">Data Target:</span>
                <span class="info-value">{{ \Carbon\Carbon::parse($nextMilestone->pivot->target_date)->format('d/m/Y') }}</span>
            </div>
            @endif
        </div>
        <p>
            Il nostro team sta già lavorando sulla prossima fase del progetto per garantire il rispetto delle tempistiche concordate.
        </p>
        @else
        <div style="background-color: #fef3c7; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #f59e0b;">
            <p style="margin: 0; color: #92400e;">
                <strong>Ultima milestone completata!</strong><br>
                Tutte le milestone del progetto sono state completate. A breve riceverà ulteriori comunicazioni riguardo la conclusione del progetto.
            </p>
        </div>
        @endif

        @if($project->due_date)
        <div class="info-row" style="margin-top: 20px;">
            <span class="info-label">Scadenza Progetto:</span>
            <span class="info-value">{{ $project->due_date->format('d/m/Y') }}</span>
        </div>
        @endif

        <div style="background-color: #f9fafb; border-radius: 8px; padding: 20px; margin: 30px 0; border: 1px solid #e5e7eb;">
            <p style="margin: 0 0 10px 0; font-weight: bold; color: #4b5563;">
                📧 Contatti
            </p>
            <p style="margin: 0 0 15px 0; color: #6b7280; font-size: 14px;">
                Per qualsiasi domanda o chiarimento sul progresso del progetto, non esiti a contattarci:
            </p>
            <div style="margin-left: 10px;">
                <p style="margin: 5px 0;">
                    <a href="mailto:alessandro.cursoli@supernovaindustries.it" style="color: #10b981; text-decoration: none; font-weight: bold;">
                        ✉️ alessandro.cursoli@supernovaindustries.it
                    </a>
                </p>
                <p style="margin: 5px 0;">
                    <a href="mailto:info@supernovaindustries.it" style="color: #10b981; text-decoration: none; font-weight: bold;">
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
