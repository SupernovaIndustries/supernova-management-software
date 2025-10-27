<div class="section bom">
    <h2>{{ $section['title'] }}</h2>
    
    @if($data['bom_items']->isNotEmpty())
    <table class="bom-table">
        <thead>
            <tr>
                <th>Pos.</th>
                <th>Designator</th>
                <th>Componente</th>
                <th>Valore</th>
                <th>Package</th>
                <th>Produttore</th>
                <th>Part Number</th>
                <th>Qty</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['bom_items'] as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->designator ?? '—' }}</td>
                <td>{{ $item->component->name ?? '—' }}</td>
                <td>{{ $item->value ?? $item->component->value ?? '—' }}</td>
                <td>{{ $item->footprint ?? $item->component->footprint ?? '—' }}</td>
                <td>{{ $item->component->manufacturer ?? '—' }}</td>
                <td>{{ $item->component->part_number ?? '—' }}</td>
                <td>{{ $item->quantity ?? 1 }}</td>
                <td>{{ $item->notes ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    
    <div style="margin-top: 20px;">
        <h3>Riepilogo BOM</h3>
        <table class="specs-table">
            <tr>
                <th>Parametro</th>
                <th>Valore</th>
            </tr>
            <tr>
                <td>Numero totale componenti unici</td>
                <td>{{ $data['bom_items']->count() }}</td>
            </tr>
            <tr>
                <td>Quantità totale componenti</td>
                <td>{{ $data['bom_items']->sum('quantity') }}</td>
            </tr>
            @if($data['bom_items']->where('component.price_eur', '>', 0)->count() > 0)
            <tr>
                <td>Costo stimato BOM (EUR)</td>
                <td>€ {{ number_format($data['bom_items']->sum(function($item) {
                    return ($item->component->price_eur ?? 0) * ($item->quantity ?? 1);
                }), 2) }}</td>
            </tr>
            @endif
        </table>
    </div>
    @else
    <p><em>BOM non disponibile per questo progetto.</em></p>
    @endif
</div>