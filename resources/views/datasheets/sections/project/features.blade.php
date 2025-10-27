<div class="section features">
    <h2>{{ $section['title'] }}</h2>
    
    @if(!empty($data['features']))
    <ul style="list-style-type: disc; padding-left: 20px;">
        @foreach($data['features'] as $feature)
        <li style="margin-bottom: 8px;">{{ trim($feature) }}</li>
        @endforeach
    </ul>
    @endif
    
    @if($data['system_instances']->isNotEmpty())
    <h3>Caratteristiche per Sistema</h3>
    @foreach($data['system_instances'] as $instance)
        <h4>🔧 {{ $instance->instance_name }}</h4>
        <ul style="list-style-type: circle; padding-left: 20px;">
            <li>Categoria: {{ $instance->systemVariant->category->display_name }}</li>
            <li>Variante: {{ $instance->systemVariant->display_name }}</li>
            @if($instance->systemVariant->specifications)
                @foreach($instance->systemVariant->specifications as $key => $value)
                <li>{{ $key }}: {{ $value }}</li>
                @endforeach
            @endif
            @if($instance->custom_notes)
            <li>Note: {{ $instance->custom_notes }}</li>
            @endif
        </ul>
    @endforeach
    @endif
    
    @if($data['milestones']->isNotEmpty())
    <h3>Milestone del Progetto</h3>
    <table class="specs-table">
        <thead>
            <tr>
                <th>Milestone</th>
                <th>Descrizione</th>
                <th>Data Target</th>
                <th>Stato</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['milestones'] as $milestone)
            <tr>
                <td>{{ $milestone->name }}</td>
                <td>{{ $milestone->description ?? '—' }}</td>
                <td>{{ $milestone->pivot->due_date ? $milestone->pivot->due_date->format('d/m/Y') : '—' }}</td>
                <td>
                    @if($milestone->pivot->is_completed)
                        ✅ Completata
                    @else
                        ⏳ In corso
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
    
    @if(empty($data['features']) && $data['system_instances']->isEmpty() && $data['milestones']->isEmpty())
    <p><em>Nessuna caratteristica specifica documentata per questo progetto.</em></p>
    @endif
</div>