@php
    /** @var \App\Models\Turma $turma */
    $isEdit = $turma->exists;
    $popupOpcoes = old('popup_opcoes', $turma->popup_opcoes ?? []);
    if (!is_array($popupOpcoes) || count($popupOpcoes) === 0) {
        $popupOpcoes = [['label' => '', 'url' => '']];
    }
@endphp

@if($errors->any())
    <div class="p-4 mb-4 bg-red-100 text-red-800 rounded border border-red-300">
        <p class="font-bold">Erro ao salvar turma:</p>
        <ul class="list-disc list-inside mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ $isEdit ? route('turmas.update', $turma) : route('turmas.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6 max-w-4xl">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <section class="bg-white p-6 rounded shadow">
        <h2 class="text-lg font-bold text-gray-800 mb-1">Cadastro interno</h2>
        <p class="text-sm text-gray-500 mb-6">Uso da equipe. O aluno não vê o nome interno.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <label class="block md:col-span-2">
                <span class="text-sm font-semibold text-gray-700">Nome interno *</span>
                <input type="text" name="title" required class="mt-2 w-full rounded border border-gray-300 p-3" value="{{ old('title', $turma->title) }}" placeholder="Ex.: PRF Policial 2026">
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-gray-700">Slug</span>
                <input type="text" name="slug" class="mt-2 w-full rounded border border-gray-300 p-3" value="{{ old('slug', $turma->slug) }}" placeholder="prf-policial-2026">
                <span class="text-xs text-gray-500">Identificador para URLs. Vazio gera a partir do nome interno.</span>
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-gray-700">Ordem de exibição</span>
                <input type="number" min="0" name="ordem_exibicao" class="mt-2 w-full rounded border border-gray-300 p-3" value="{{ old('ordem_exibicao', $turma->ordem_exibicao) }}">
                <span class="text-xs text-gray-500">Menor número aparece primeiro na seção.</span>
            </label>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-6">
            <label class="inline-flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="ativo" value="1" class="mt-1 h-4 w-4" {{ old('ativo', $turma->ativo) ? 'checked' : '' }}>
                <span><span class="text-sm font-semibold text-gray-700">Ativo?</span><span class="block text-xs text-gray-500">Não = cadastro arquivado/histórico.</span></span>
            </label>
            <label class="inline-flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="exibir_no_site" value="1" class="mt-1 h-4 w-4" {{ old('exibir_no_site', $turma->exibir_no_site) ? 'checked' : '' }}>
                <span><span class="text-sm font-semibold text-gray-700">Exibir no site?</span><span class="block text-xs text-gray-500">Controla se a preparação aparece para o público.</span></span>
            </label>
            <label class="inline-flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="plano_pronto_tutory" value="1" class="mt-1 h-4 w-4" {{ old('plano_pronto_tutory', $turma->plano_pronto_tutory) ? 'checked' : '' }}>
                <span><span class="text-sm font-semibold text-gray-700">Plano pronto na Tutory?</span><span class="block text-xs text-gray-500">Já existe estrutura para receber aluno.</span></span>
            </label>
            <label class="inline-flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="aceitar_novos_alunos" value="1" class="mt-1 h-4 w-4" {{ old('aceitar_novos_alunos', $turma->aceitar_novos_alunos) ? 'checked' : '' }}>
                <span><span class="text-sm font-semibold text-gray-700">Aceitar novos alunos agora?</span><span class="block text-xs text-gray-500">Não + visível no site = badge “Em breve”.</span></span>
            </label>
        </div>
    </section>

    <section class="bg-white p-6 rounded shadow">
        <h2 class="text-lg font-bold text-gray-800 mb-1">Classificação e busca</h2>
        <p class="text-sm text-gray-500 mb-6">Define seção, filtro e termos usados em /turmas-abertas.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <label class="block">
                <span class="text-sm font-semibold text-gray-700">Grupo de exibição *</span>
                <select name="grupo_exibicao" class="mt-2 w-full rounded border border-gray-300 p-3">
                    @foreach(\App\Models\Turma::GRUPOS_EXIBICAO as $valor => $label)
                        <option value="{{ $valor }}" {{ old('grupo_exibicao', $turma->grupo_exibicao) === $valor ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-gray-700">Categoria de navegação</span>
                <input list="categorias-navegacao" type="text" name="categoria_navegacao" class="mt-2 w-full rounded border border-gray-300 p-3" value="{{ old('categoria_navegacao', $turma->categoria_navegacao) }}" placeholder="Policiais / Administrativas / Tribunais">
                <datalist id="categorias-navegacao">
                    @foreach(\App\Models\Turma::CATEGORIAS_NAVEGACAO as $categoria)
                        <option value="{{ $categoria }}"></option>
                    @endforeach
                </datalist>
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-gray-700">Concurso/órgão relacionado</span>
                <input list="orgaos-turma" type="text" name="orgao" class="mt-2 w-full rounded border border-gray-300 p-3" value="{{ old('orgao', $turma->orgao) }}" placeholder="PRF, PMDF, PCDF">
                <datalist id="orgaos-turma">
                    @foreach(\App\Models\Turma::ORGAOS as $orgao)
                        <option value="{{ $orgao }}"></option>
                    @endforeach
                </datalist>
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-gray-700">Cargo</span>
                <input list="cargos-turma" type="text" name="cargo" class="mt-2 w-full rounded border border-gray-300 p-3" value="{{ old('cargo', $turma->cargo) }}" placeholder="Agente, Escrivão, Praça">
                <datalist id="cargos-turma">
                    @foreach(\App\Models\Turma::CARGOS as $cargo)
                        <option value="{{ $cargo }}"></option>
                    @endforeach
                </datalist>
            </label>
            <label class="block md:col-span-2">
                <span class="text-sm font-semibold text-gray-700">Termos de busca</span>
                <input type="text" name="termos_busca" class="mt-2 w-full rounded border border-gray-300 p-3" value="{{ old('termos_busca', $turma->termos_busca) }}" placeholder="PF, Polícia Federal, Agente PF">
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-gray-700">Momento do concurso</span>
                <select name="momento_concurso" class="mt-2 w-full rounded border border-gray-300 p-3">
                    <option value="">—</option>
                    @foreach(\App\Models\Turma::MOMENTOS_CONCURSO as $valor => $label)
                        <option value="{{ $valor }}" {{ old('momento_concurso', $turma->momento_concurso) === $valor ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="inline-flex items-center gap-3 cursor-pointer mt-8">
                <input type="checkbox" name="exibir_momento_concurso" value="1" class="h-4 w-4" {{ old('exibir_momento_concurso', $turma->exibir_momento_concurso) ? 'checked' : '' }}>
                <span class="text-sm font-semibold text-gray-700">Exibir momento do concurso no card?</span>
            </label>
        </div>
    </section>

    <section class="bg-white p-6 rounded shadow">
        <h2 class="text-lg font-bold text-gray-800 mb-1">O que o aluno vê</h2>
        <p class="text-sm text-gray-500 mb-6">Campos públicos do card no Missão Nomeação.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <label class="block">
                <span class="text-sm font-semibold text-gray-700">Nome público</span>
                <input type="text" name="nome_publico" class="mt-2 w-full rounded border border-gray-300 p-3" value="{{ old('nome_publico', $turma->nome_publico) }}" placeholder="Ex.: PRF — Policial">
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-gray-700">Seção da página</span>
                <input list="secoes-pagina" type="text" name="secao_pagina" class="mt-2 w-full rounded border border-gray-300 p-3" value="{{ old('secao_pagina', $turma->secao_pagina) }}" placeholder="Ainda está formando sua base?">
                <datalist id="secoes-pagina">
                    @foreach(\App\Models\Turma::SECOES_PAGINA as $secao)
                        <option value="{{ $secao }}"></option>
                    @endforeach
                </datalist>
            </label>
            <label class="block md:col-span-2">
                <span class="text-sm font-semibold text-gray-700">Descrição curta</span>
                <textarea name="description" rows="3" class="mt-2 w-full rounded border border-gray-300 p-3" placeholder="1 ou 2 frases explicando aquela preparação">{{ old('description', $turma->description) }}</textarea>
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-gray-700">Texto do CTA</span>
                <input type="text" name="texto_cta" class="mt-2 w-full rounded border border-gray-300 p-3" value="{{ old('texto_cta', $turma->texto_cta) }}" placeholder="COMEÇAR AGORA">
                <span class="text-xs text-gray-500">Vazio usa o padrão do estágio (COMEÇAR AGORA, QUERO SER AVISADO ou ENTRAR NO GRUPO).</span>
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-gray-700">Imagem/capa</span>
                @if($turma->logo_path)
                    <div class="mb-2">
                        <img src="{{ asset('storage/' . $turma->logo_path) }}" alt="{{ $turma->title }}" class="h-20 w-20 object-cover rounded border border-gray-200">
                    </div>
                @endif
                <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="mt-2 w-full p-3 border border-gray-300 rounded">
                <span class="text-xs text-gray-500">PNG, JPG, WEBP ou SVG (máx 5MB){{ $isEdit ? ' — vazio mantém a atual' : '' }}.</span>
            </label>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-6">
            <label class="inline-flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="destacar_turmas_abertas" value="1" class="mt-1 h-4 w-4" {{ old('destacar_turmas_abertas', $turma->destacar_turmas_abertas) ? 'checked' : '' }}>
                <span><span class="text-sm font-semibold text-gray-700">Destacar em /turmas-abertas?</span><span class="block text-xs text-gray-500">Prioridade comercial na listagem.</span></span>
            </label>
            <label class="inline-flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="exibir_na_mentoria" value="1" class="mt-1 h-4 w-4" {{ old('exibir_na_mentoria', $turma->exibir_na_mentoria) ? 'checked' : '' }}>
                <span><span class="text-sm font-semibold text-gray-700">Exibir também na /mentoria?</span><span class="block text-xs text-gray-500">Aparece na landing principal.</span></span>
            </label>
        </div>
    </section>

    <section class="bg-white p-6 rounded shadow">
        <h2 class="text-lg font-bold text-gray-800 mb-1">Ação, links e popup</h2>
        <p class="text-sm text-gray-500 mb-6">O estágio público sai da combinação “aceitar alunos” + ação principal.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <label class="block md:col-span-2">
                <span class="text-sm font-semibold text-gray-700">Ação principal do site *</span>
                <select name="acao_principal" class="mt-2 w-full rounded border border-gray-300 p-3">
                    @foreach(\App\Models\Turma::ACOES_PRINCIPAIS as $valor => $label)
                        <option value="{{ $valor }}" {{ old('acao_principal', $turma->acao_principal) === $valor ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-gray-700">Link do checkout</span>
                <input type="url" name="checkout_url" class="mt-2 w-full rounded border border-gray-300 p-3" value="{{ old('checkout_url', $turma->checkout_url) }}" placeholder="https://">
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-gray-700">Link do WhatsApp</span>
                <input type="url" name="whatsapp_url" class="mt-2 w-full rounded border border-gray-300 p-3" value="{{ old('whatsapp_url', $turma->whatsapp_url) }}" placeholder="https://wa.me/">
            </label>
            <label class="block md:col-span-2">
                <span class="text-sm font-semibold text-gray-700">Link de interesse</span>
                <input type="url" name="interesse_url" class="mt-2 w-full rounded border border-gray-300 p-3" value="{{ old('interesse_url', $turma->interesse_url) }}" placeholder="https://">
            </label>
        </div>

        <div class="mt-6">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">Opções do popup</h3>
                    <p class="text-xs text-gray-500">Ex.: Agente Administrativo / Analista + destino de cada uma.</p>
                </div>
                <button type="button" id="add-popup-option" class="text-sm px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded">+ Opção</button>
            </div>
            <div id="popup-options" class="space-y-3">
                @foreach($popupOpcoes as $i => $opcao)
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 popup-option-row">
                        <input type="text" name="popup_opcoes[{{ $i }}][label]" class="md:col-span-5 rounded border border-gray-300 p-3" value="{{ $opcao['label'] ?? '' }}" placeholder="Nome da opção">
                        <input type="url" name="popup_opcoes[{{ $i }}][url]" class="md:col-span-6 rounded border border-gray-300 p-3" value="{{ $opcao['url'] ?? '' }}" placeholder="https://destino">
                        <button type="button" class="md:col-span-1 px-3 py-2 bg-red-50 text-red-700 rounded remove-popup-option">×</button>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-white p-6 rounded shadow">
        <h2 class="text-lg font-bold text-gray-800 mb-1">Operação</h2>
        <p class="text-sm text-gray-500 mb-6">Vagas e data usadas nas inscrições deste backend. Status aberto/fechado segue “aceitar novos alunos”.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <label class="block">
                <span class="text-sm font-semibold text-gray-700">Data de início</span>
                <input type="date" name="start_date" class="mt-2 w-full rounded border border-gray-300 p-3" value="{{ old('start_date', $turma->start_date?->format('Y-m-d')) }}">
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-gray-700">Vagas disponíveis</span>
                <input type="number" min="0" name="available_slots" class="mt-2 w-full rounded border border-gray-300 p-3" value="{{ old('available_slots', $turma->available_slots) }}" placeholder="Vazio = ilimitadas">
            </label>
        </div>
    </section>

    <div class="flex justify-end gap-3">
        <a href="{{ route('turmas.index') }}" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded font-medium">Cancelar</a>
        <button type="submit" class="px-6 py-3 bg-primary hover:bg-primary-light text-white rounded font-medium">{{ $isEdit ? 'Atualizar turma' : 'Criar turma' }}</button>
    </div>
</form>

<script>
    (() => {
        const container = document.getElementById('popup-options');
        const addBtn = document.getElementById('add-popup-option');
        if (!container || !addBtn) return;

        const reindex = () => {
            container.querySelectorAll('.popup-option-row').forEach((row, index) => {
                const label = row.querySelector('input[type="text"]');
                const url = row.querySelector('input[type="url"]');
                if (label) label.name = `popup_opcoes[${index}][label]`;
                if (url) url.name = `popup_opcoes[${index}][url]`;
            });
        };

        addBtn.addEventListener('click', () => {
            const row = document.createElement('div');
            row.className = 'grid grid-cols-1 md:grid-cols-12 gap-3 popup-option-row';
            row.innerHTML = `
                <input type="text" name="popup_opcoes[][label]" class="md:col-span-5 rounded border border-gray-300 p-3" placeholder="Nome da opção">
                <input type="url" name="popup_opcoes[][url]" class="md:col-span-6 rounded border border-gray-300 p-3" placeholder="https://destino">
                <button type="button" class="md:col-span-1 px-3 py-2 bg-red-50 text-red-700 rounded remove-popup-option">×</button>
            `;
            container.appendChild(row);
            reindex();
        });

        container.addEventListener('click', (event) => {
            if (!event.target.classList.contains('remove-popup-option')) return;
            const rows = container.querySelectorAll('.popup-option-row');
            if (rows.length <= 1) {
                rows[0].querySelectorAll('input').forEach((input) => input.value = '');
                return;
            }
            event.target.closest('.popup-option-row').remove();
            reindex();
        });
    })();
</script>
