<div class="section overview">
    <h2>{{ $section['title'] }}</h2>
    
    <div class="overview-content">
        @if($data['project_data']?->overview_text)
            <p>{!! nl2br(e($data['project_data']->overview_text)) !!}</p>
        @else
            <p>{{ $data['description'] ?? 'Descrizione del progetto non disponibile.' }}</p>
        @endif
        
        @if($data['project_data']?->target_market)
        <h3>Mercato di Destinazione</h3>
        <p>{{ $data['project_data']->target_market }}</p>
        @endif
        
        @if($data['project_data']?->applications)
        <h3>Applicazioni</h3>
        <p>{!! nl2br(e($data['project_data']->applications)) !!}</p>
        @endif
        
        <h3>Informazioni Progetto</h3>
        <table class="specs-table">
            <tr>
                <th>Parametro</th>
                <th>Valore</th>
            </tr>
            <tr>
                <td>Nome Progetto</td>
                <td>{{ $data['model']->name }}</td>
            </tr>
            @if($data['model']->customer)
            <tr>
                <td>Cliente</td>
                <td>{{ $data['model']->customer->name }}</td>
            </tr>
            @endif
            @if($data['model']->status)
            <tr>
                <td>Stato</td>
                <td>{{ ucfirst($data['model']->status) }}</td>
            </tr>
            @endif
            @if($data['model']->start_date)
            <tr>
                <td>Data Inizio</td>
                <td>{{ $data['model']->start_date->format('d/m/Y') }}</td>
            </tr>
            @endif
            @if($data['model']->due_date)
            <tr>
                <td>Data Consegna Prevista</td>
                <td>{{ $data['model']->due_date->format('d/m/Y') }}</td>
            </tr>
            @endif
            @if($data['system_instances']->isNotEmpty())
            <tr>
                <td>Sistemi Implementati</td>
                <td>{{ $data['system_instances']->count() }} sistemi</td>
            </tr>
            @endif
        </table>
    </div>
</div>