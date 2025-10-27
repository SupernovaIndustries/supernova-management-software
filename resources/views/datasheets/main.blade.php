<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $data['title'] }} - Datasheet</title>
    <style>
        /* Base styling for datasheet */
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 210mm;
            margin: 0 auto;
            padding: 20mm;
            background: white;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #007acc;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .company-info {
            flex: 1;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #007acc;
            margin: 0;
        }
        
        .company-tagline {
            font-size: 14px;
            color: #666;
            margin: 5px 0;
        }
        
        .logo {
            max-height: 60px;
            max-width: 120px;
        }
        
        .document-title {
            text-align: center;
            margin: 30px 0;
        }
        
        .document-title h1 {
            font-size: 28px;
            color: #007acc;
            margin: 0;
        }
        
        .document-subtitle {
            font-size: 16px;
            color: #666;
            margin: 10px 0;
        }
        
        .toc {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 30px 0;
        }
        
        .toc h2 {
            margin-top: 0;
            color: #007acc;
        }
        
        .toc ul {
            list-style: none;
            padding: 0;
        }
        
        .toc li {
            padding: 5px 0;
            border-bottom: 1px dotted #ddd;
        }
        
        .section {
            margin: 40px 0;
            page-break-inside: avoid;
        }
        
        .section h2 {
            color: #007acc;
            border-bottom: 2px solid #007acc;
            padding-bottom: 10px;
            font-size: 20px;
        }
        
        .section h3 {
            color: #333;
            font-size: 16px;
            margin-top: 25px;
        }
        
        .specs-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .specs-table th,
        .specs-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        
        .specs-table th {
            background: #007acc;
            color: white;
            font-weight: bold;
        }
        
        .specs-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .bom-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 12px;
        }
        
        .bom-table th,
        .bom-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .bom-table th {
            background: #007acc;
            color: white;
        }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        /* Custom styles from template */
        @if($template->styles)
            {!! collect($template->styles)->map(function($value, $key) {
                return ".custom-{$key} { {$value} }";
            })->join("\n") !!}
        @endif
    </style>
</head>
<body>
    @if($include_company_info)
    <div class="header">
        <div class="company-info">
            <h1 class="company-name">{{ $data['company']->company_name ?? 'Supernova Electronics' }}</h1>
            <p class="company-tagline">{{ $data['company']->tagline ?? 'Advanced Electronics Solutions' }}</p>
            <p>{{ $data['company']->address ?? '' }}</p>
            <p>{{ $data['company']->email ?? '' }} | {{ $data['company']->phone ?? '' }}</p>
        </div>
        @if($template->logo_path)
        <div class="logo-container">
            <img src="{{ Storage::url($template->logo_path) }}" alt="Logo" class="logo">
        </div>
        @endif
    </div>
    @endif

    <div class="document-title">
        <h1>{{ $data['title'] }}</h1>
        <p class="document-subtitle">Datasheet Tecnico</p>
        <p class="document-subtitle">Generato il {{ $data['generated_at']->format('d/m/Y H:i') }}</p>
    </div>

    @if($include_toc && count($sections) > 3)
    <div class="toc">
        <h2>Indice</h2>
        <ul>
            @foreach($sections as $index => $section)
                @php
                    $sectionData = collect($template->sections)->firstWhere('name', $section['name']);
                @endphp
                <li>{{ $index + 1 }}. {{ $sectionData['title'] ?? 'Sezione' }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @foreach($sections as $sectionContent)
        {!! $sectionContent !!}
    @endforeach

    <div class="footer">
        <p>Documento generato automaticamente da Supernova Management System</p>
        <p>{{ $data['company']->company_name ?? 'Supernova Electronics' }} - Tutti i diritti riservati</p>
        <p>{{ now()->format('d/m/Y H:i') }}</p>
    </div>
</body>
</html>