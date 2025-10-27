<div class="section specifications">
    <h2>{{ $section['title'] }}</h2>
    
    @if(!empty($data['specifications']))
    <table class="specs-table">
        <thead>
            <tr>
                <th>Parametro</th>
                <th>Valore</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['specifications'] as $key => $value)
                <tr>
                    <td>{{ is_string($key) ? $key : 'Specifica' }}</td>
                    <td>
                        @if(is_array($value))
                            {{ implode(', ', $value) }}
                        @else
                            {{ $value }}
                        @endif
                    </td>
                    <td>—</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @endif
    
    @if($data['system_instances']->isNotEmpty())
    <h3>Specifiche per Sistema</h3>
    @foreach($data['system_instances'] as $instance)
        <h4>{{ $instance->instance_name }}</h4>
        @if($instance->custom_specifications)
        <table class="specs-table">
            <thead>
                <tr>
                    <th>Parametro</th>
                    <th>Valore</th>
                </tr>
            </thead>
            <tbody>
                @foreach($instance->custom_specifications as $key => $value)
                <tr>
                    <td>{{ $key }}</td>
                    <td>{{ is_array($value) ? implode(', ', $value) : $value }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p><em>Nessuna specifica personalizzata per questo sistema.</em></p>
        @endif
    @endforeach
    @endif
    
    @if($data['project_data']?->custom_specifications)
    <h3>Specifiche Personalizzate</h3>
    <table class="specs-table">
        <thead>
            <tr>
                <th>Parametro</th>
                <th>Valore</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['project_data']->custom_specifications as $key => $value)
            <tr>
                <td>{{ $key }}</td>
                <td>{{ is_array($value) ? implode(', ', $value) : $value }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
    
    @if(empty($data['specifications']) && $data['system_instances']->isEmpty() && !$data['project_data']?->custom_specifications)
    <p><em>Nessuna specifica tecnica disponibile per questo progetto.</em></p>
    @endif
</div>