@php
    $cid = $regra['criterio_desempenho_id'] ?? '';
    $op = $regra['operador'] ?? '>=';
    $vmin = $regra['valor_min'] ?? '';
    $vmax = $regra['valor_max'] ?? '';
@endphp
<div class="regra-row grid grid-cols-1 md:grid-cols-12 gap-3 p-3 border border-gray-200 rounded bg-gray-50">
    <div class="md:col-span-4">
        <label class="block text-xs font-semibold text-gray-600 mb-1">Critério</label>
        <select name="regras[{{ $index }}][criterio_desempenho_id]" required class="w-full rounded border border-gray-300 p-2 text-sm">
            <option value="">Selecione</option>
            @foreach($criterios as $criterio)
                <option value="{{ $criterio->id }}" @selected((string) $cid === (string) $criterio->id)>
                    {{ $criterio->nome }}@if($criterio->unidade) ({{ $criterio->unidade }})@endif
                </option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="block text-xs font-semibold text-gray-600 mb-1">Operador</label>
        <select name="regras[{{ $index }}][operador]" required class="regra-operador w-full rounded border border-gray-300 p-2 text-sm">
            @foreach($operadores as $operador)
                <option value="{{ $operador }}" @selected($op === $operador)>
                    @if($operador === 'between') entre
                    @elseif($operador === '>=') ≥
                    @elseif($operador === '<=') ≤
                    @else {{ $operador }}
                    @endif
                </option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="block text-xs font-semibold text-gray-600 mb-1">Valor</label>
        <input type="number" step="0.01" name="regras[{{ $index }}][valor_min]" class="w-full rounded border border-gray-300 p-2 text-sm" value="{{ $vmin }}" placeholder="Ex: 80">
    </div>
    <div class="md:col-span-2 valor-max-wrap {{ $op === 'between' ? '' : 'hidden' }}">
        <label class="block text-xs font-semibold text-gray-600 mb-1">Até</label>
        <input type="number" step="0.01" name="regras[{{ $index }}][valor_max]" class="w-full rounded border border-gray-300 p-2 text-sm" value="{{ $vmax }}" placeholder="Máx">
    </div>
    <div class="md:col-span-2 flex items-end">
        <button type="button" class="btn-remove-regra w-full px-3 py-2 bg-white border border-gray-300 hover:bg-gray-100 text-gray-700 rounded text-sm">Remover</button>
    </div>
</div>
