@php
    /** @var \Illuminate\Support\Collection|\App\Models\CriterioDesempenho[] $criterios */
    $nivelNome = old('nome', isset($nivel) ? $nivel->nome : '');
    $nivelOrdem = old('ordem', isset($nivel) ? $nivel->ordem : 1);
    $nivelTexto = old('texto_email', isset($nivel) ? $nivel->texto_email : '');
    $nivelAtivo = old('ativo', isset($nivel) ? ($nivel->ativo ? '1' : null) : '1');
@endphp

<div class="mb-6">
    <label class="block">
        <span class="text-sm font-semibold text-gray-700">Nome do nível *</span>
        <input type="text" name="nome" required class="mt-2 w-full rounded border border-gray-300 p-3 focus:ring-primary focus:border-primary" value="{{ $nivelNome }}" placeholder="Ex: Excelente, Bom, Regular">
    </label>
</div>

<div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
    <label class="block">
        <span class="text-sm font-semibold text-gray-700">Ordem de avaliação *</span>
        <input type="number" name="ordem" min="1" required class="mt-2 w-full rounded border border-gray-300 p-3 focus:ring-primary focus:border-primary" value="{{ $nivelOrdem }}">
        <span class="text-xs text-gray-500">Menor número = avaliado primeiro (ex.: Excelente = 1).</span>
    </label>
    <label class="inline-flex items-center gap-3 cursor-pointer mt-8">
        <input type="checkbox" name="ativo" value="1" class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary" {{ $nivelAtivo ? 'checked' : '' }}>
        <span class="text-sm font-semibold text-gray-700">Nível ativo</span>
    </label>
</div>

<div class="mb-6">
    <label class="block">
        <span class="text-sm font-semibold text-gray-700">Texto no e-mail do aluno *</span>
        <textarea name="texto_email" rows="4" required class="mt-2 w-full rounded border border-gray-300 p-3 focus:ring-primary focus:border-primary" placeholder="Ex: Parabéns! Seu desempenho neste período foi classificado como Excelente.">{{ $nivelTexto }}</textarea>
    </label>
</div>

<div class="mb-6">
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-lg font-semibold text-gray-800">Critérios (1 ou mais)</h2>
        <button type="button" id="btn-add-regra" class="px-3 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded text-sm">+ Critério</button>
    </div>
    <p class="text-xs text-gray-500 mb-3">Todos os critérios do nível precisam ser atendidos. Horas aceitam valor decimal (ex.: 10.5 = 10h30).</p>

    <div id="regras-wrap" class="space-y-3">
        @foreach($regras as $i => $regra)
            @include('admin.desempenho._regra_row', ['index' => $i, 'regra' => $regra])
        @endforeach
    </div>
</div>

<div class="flex justify-end gap-3">
    <a href="{{ route('desempenho.index') }}" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded font-medium transition">Cancelar</a>
    <button type="submit" class="px-6 py-3 bg-primary hover:bg-primary-light text-white rounded font-medium transition">Salvar</button>
</div>

<template id="regra-template">
    @include('admin.desempenho._regra_row', [
        'index' => '__INDEX__',
        'regra' => ['criterio_desempenho_id' => '', 'operador' => '>=', 'valor_min' => '', 'valor_max' => ''],
    ])
</template>

<script>
(function () {
    const wrap = document.getElementById('regras-wrap');
    const tpl = document.getElementById('regra-template');
    const btn = document.getElementById('btn-add-regra');
    if (!wrap || !tpl || !btn) return;

    let idx = wrap.querySelectorAll('.regra-row').length;

    function toggleMax(row) {
        const op = row.querySelector('.regra-operador');
        const maxWrap = row.querySelector('.valor-max-wrap');
        if (!op || !maxWrap) return;
        maxWrap.classList.toggle('hidden', op.value !== 'between');
    }

    wrap.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('regra-operador')) {
            toggleMax(e.target.closest('.regra-row'));
        }
    });

    wrap.addEventListener('click', function (e) {
        const btnRem = e.target.closest('.btn-remove-regra');
        if (!btnRem) return;
        const rows = wrap.querySelectorAll('.regra-row');
        if (rows.length <= 1) return;
        btnRem.closest('.regra-row').remove();
    });

    btn.addEventListener('click', function () {
        const html = tpl.innerHTML.replaceAll('__INDEX__', String(idx));
        wrap.insertAdjacentHTML('beforeend', html);
        const row = wrap.lastElementChild;
        toggleMax(row);
        idx += 1;
    });

    wrap.querySelectorAll('.regra-row').forEach(toggleMax);
})();
</script>
