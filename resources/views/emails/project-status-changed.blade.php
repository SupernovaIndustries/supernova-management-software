<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aggiornamento Stato Progetto</title>
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
            border-bottom: 3px solid #2563eb;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2563eb;
            margin: 0;
            font-size: 24px;
        }
        .status-change {
            background-color: #f0f9ff;
            border-left: 4px solid #2563eb;
            padding: 15px;
            margin: 20px 0;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 14px;
        }
        .status-old {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .status-new {
            background-color: #dcfce7;
            color: #166534;
        }
        .status-in-progress {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .status-completed {
            background-color: #dcfce7;
            color: #166534;
        }
        .status-cancelled {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .status-on-hold {
            background-color: #fef3c7;
            color: #92400e;
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
            width: 150px;
        }
        .info-value {
            color: #1f2937;
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
            color: #2563eb;
        }
        .arrow {
            display: inline-block;
            margin: 0 10px;
            color: #2563eb;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>Aggiornamento Stato Progetto</h1>
        </div>

        <p>Spettabile <strong>{{ $project->customer->company_name ?? $customerName }}</strong>,</p>

        @if($oldStatus === null)
            <p>La informiamo che è stato inizializzato un nuovo progetto.</p>
        @else
            <p>La informiamo che lo stato del progetto è stato aggiornato.</p>

            <div class="status-change">
                <p style="margin: 0; font-weight: bold; margin-bottom: 10px;">Cambio di Stato:</p>
                <div style="text-align: center; padding: 10px 0;">
                    <span class="status-badge status-old">{{ $oldStatusLabel }}</span>
                    <span class="arrow">→</span>
                    <span class="status-badge status-{{ $newStatus }}">{{ $newStatusLabel }}</span>
                </div>
            </div>
        @endif

        <div class="project-info">
            <div class="info-row">
                <span class="info-label">Codice Progetto:</span>
                <span class="info-value">{{ $project->code }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Nome Progetto:</span>
                <span class="info-value">{{ $project->name }}</span>
            </div>
            @if($oldStatus !== null)
            <div class="info-row">
                <span class="info-label">Data Cambio:</span>
                <span class="info-value">{{ $changeDate }}</span>
            </div>
            @else
            <div class="info-row">
                <span class="info-label">Data Creazione:</span>
                <span class="info-value">{{ $changeDate }}</span>
            </div>
            @endif
            @if($oldStatus === null)
            <div class="info-row">
                <span class="info-label">Stato Iniziale:</span>
                <span class="info-value">{{ $newStatusLabel }}</span>
            </div>
            @endif
            @if($project->due_date)
            <div class="info-row">
                <span class="info-label">Scadenza Prevista:</span>
                <span class="info-value">{{ $project->due_date->format('d/m/Y') }}</span>
            </div>
            @endif
        </div>

        @if($project->description)
        <div style="margin: 20px 0;">
            <p style="font-weight: bold; color: #4b5563; margin-bottom: 5px;">Descrizione Progetto:</p>
            <p style="color: #6b7280; font-style: italic;">{{ $project->description }}</p>
        </div>
        @endif

        @if($oldStatus === null && $milestones && $milestones->count() > 0)
        <div style="margin: 30px 0;">
            <p style="font-weight: bold; color: #4b5563; margin-bottom: 15px; font-size: 16px;">📋 Milestone del Progetto:</p>
            <div style="background-color: #f9fafb; border-radius: 8px; padding: 15px;">
                @foreach($milestones as $milestone)
                <div style="background-color: white; border-left: 4px solid #2563eb; padding: 12px; margin-bottom: 10px; border-radius: 4px;">
                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                        <span style="font-weight: bold; color: #1f2937; font-size: 14px;">{{ $loop->iteration }}. {{ $milestone->name }}</span>
                        <span style="display: inline-block; margin-left: 10px; padding: 2px 8px; background-color: #dbeafe; color: #1e40af; border-radius: 3px; font-size: 11px; text-transform: uppercase;">
                            {{ $milestone->category }}
                        </span>
                    </div>
                    @if($milestone->description)
                    <p style="color: #6b7280; font-size: 13px; margin: 5px 0;">{{ $milestone->description }}</p>
                    @endif
                    @if($milestone->pivot && $milestone->pivot->target_date)
                    <p style="color: #4b5563; font-size: 12px; margin: 5px 0;">
                        <strong>Data target:</strong> {{ \Carbon\Carbon::parse($milestone->pivot->target_date)->format('d/m/Y') }}
                    </p>
                    @endif
                </div>
                @endforeach
            </div>
            <p style="color: #6b7280; font-size: 13px; margin-top: 10px; font-style: italic;">
                Le riceveremo notifiche via email ad ogni completamento di milestone.
            </p>
        </div>
        @endif

        @if($newStatus === 'in_progress')
        <div style="background-color: #ecfdf5; padding: 15px; border-radius: 6px; margin: 20px 0;">
            <p style="margin: 0; color: #065f46;">
                <strong>Il progetto è ora in fase di sviluppo.</strong><br>
                Il nostro team sta lavorando attivamente per garantire la massima qualità e rispettare le tempistiche concordate.
            </p>
        </div>
        @elseif($newStatus === 'testing')
        <div style="background-color: #eff6ff; padding: 15px; border-radius: 6px; margin: 20px 0;">
            <p style="margin: 0; color: #1e40af;">
                <strong>Il progetto è in fase di test.</strong><br>
                Stiamo effettuando tutti i controlli necessari per assicurare il corretto funzionamento del prodotto.
            </p>
        </div>
        @elseif($newStatus === 'consegna_prototipo_test')
        <div style="background-color: #fef3c7; padding: 15px; border-radius: 6px; margin: 20px 0;">
            <p style="margin: 0; color: #92400e;">
                <strong>Il prototipo è pronto per i test.</strong><br>
                La contatteremo a breve per organizzare la consegna e concordare le modalità di test.
            </p>
        </div>
        @elseif($newStatus === 'completed')
        <div style="background-color: #ecfdf5; padding: 15px; border-radius: 6px; margin: 20px 0;">
            <p style="margin: 0; color: #065f46;">
                <strong>Il progetto è stato completato con successo!</strong><br>
                Grazie per aver scelto i nostri servizi. Rimaniamo a disposizione per qualsiasi supporto futuro.
            </p>
        </div>
        @elseif($newStatus === 'on_hold')
        <div style="background-color: #fef3c7; padding: 15px; border-radius: 6px; margin: 20px 0;">
            <p style="margin: 0; color: #92400e;">
                <strong>Il progetto è momentaneamente in pausa.</strong><br>
                La contatteremo non appena saremo pronti a riprendere le attività.
            </p>
        </div>
        @elseif($newStatus === 'cancelled')
        <div style="background-color: #fee2e2; padding: 15px; border-radius: 6px; margin: 20px 0;">
            <p style="margin: 0; color: #991b1b;">
                <strong>Il progetto è stato annullato.</strong><br>
                Per qualsiasi chiarimento o informazione, non esiti a contattarci.
            </p>
        </div>
        @endif

        <div style="background-color: #f9fafb; border-radius: 8px; padding: 20px; margin: 30px 0; border: 1px solid #e5e7eb;">
            <p style="margin: 0 0 10px 0; font-weight: bold; color: #4b5563;">
                📧 Contatti
            </p>
            <p style="margin: 0 0 15px 0; color: #6b7280; font-size: 14px;">
                Per qualsiasi domanda o chiarimento, non esiti a contattarci:
            </p>
            <div style="margin-left: 10px;">
                <p style="margin: 5px 0;">
                    <a href="mailto:alessandro.cursoli@supernovaindustries.it" style="color: #2563eb; text-decoration: none; font-weight: bold;">
                        ✉️ alessandro.cursoli@supernovaindustries.it
                    </a>
                </p>
                <p style="margin: 5px 0;">
                    <a href="mailto:info@supernovaindustries.it" style="color: #2563eb; text-decoration: none; font-weight: bold;">
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
