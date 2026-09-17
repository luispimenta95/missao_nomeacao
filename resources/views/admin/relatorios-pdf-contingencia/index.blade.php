@extends('layouts.admin')

@section('title', 'PDF de meses anteriores')

@section('content')
    <div>
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800">PDF de meses anteriores</h1>
            <p class="text-gray-600 mt-2">Contingência: gera o mesmo PDF de desempenho de um aluno e disponibiliza para download (sem envio por e-mail).</p>
        </div>

        @if(session('success'))
            <div class="p-4 mb-6 bg-green-100 text-green-800 rounded border border-green-300">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 mb-6 bg-red-100 text-red-800 rounded border border-red-300">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
            <form id="form-pdf-contingencia" action="{{ route('relatorios-pdf-contingencia.gerar') }}" method="POST" class="bg-white p-8 rounded shadow-lg">
                @csrf
                <input type="hidden" name="progress_token" id="progress-token" value="{{ $progressToken }}">

                <h2 class="text-lg font-bold text-gray-800 mb-1">Gerar PDF</h2>
                <p class="text-sm text-gray-500 mb-6">Um aluno por geração. Acompanhe o progresso na tela enquanto o relatório é montado.</p>

                <div class="mb-6">
                    <label class="block">
                        <span class="text-sm font-semibold text-gray-700">Aluno *</span>
                        <select id="campo-aluno" name="aluno_id" required class="mt-2 w-full rounded border border-gray-300 p-3 bg-white focus:ring-primary focus:border-primary @error('aluno_id') border-red-500 @enderror">
                            <option value="">Selecione um aluno ativo da Tutory</option>
                            @foreach($alunos as $aluno)
                                <option value="{{ $aluno->id }}" {{ (string) old('aluno_id') === (string) $aluno->id ? 'selected' : '' }}>
                                    {{ $aluno->nome }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <p class="text-xs text-gray-500 mt-2">Lista dos alunos sincronizados como ativos na Tutory.</p>
                    @if($alunos->isEmpty())
                        <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded p-3 mt-3">Nenhum aluno com id da Tutory. Rode a sincronização de alunos ativos e volte aqui.</p>
                    @endif
                </div>

                <div class="mb-8">
                    <label class="block">
                        <span class="text-sm font-semibold text-gray-700">Mês / período *</span>
                        <select id="campo-periodo" name="periodo" required class="mt-2 w-full rounded border border-gray-300 p-3 bg-white focus:ring-primary focus:border-primary @error('periodo') border-red-500 @enderror">
                            @forelse($periodos as $periodo)
                                <option value="{{ $periodo['chave'] }}" {{ (string) old('periodo', $periodos[array_key_last($periodos)]['chave'] ?? '') === (string) $periodo['chave'] ? 'selected' : '' }}>
                                    {{ $periodo['label'] }}
                                </option>
                            @empty
                                <option value="">Nenhum período liberado</option>
                            @endforelse
                        </select>
                    </label>
                    <p class="text-xs text-gray-500 mt-2">{{ count($periodos) }} de até {{ $limiteLinhas }} linhas (2 períodos × {{ $mesesVisiveis }} meses). Período 1 libera no dia 16; período 2 no dia 1 do mês seguinte.</p>
                </div>

                <div class="flex justify-end">
                    <button id="btn-gerar-pdf" type="submit" class="px-6 py-3 bg-primary hover:bg-primary-light text-white rounded font-medium transition" {{ $alunos->isEmpty() || $periodos === [] ? 'disabled' : '' }}>
                        Gerar PDF
                    </button>
                </div>
            </form>

            <form action="{{ route('relatorios-pdf-contingencia.update') }}" method="POST" class="bg-white p-8 rounded shadow-lg h-fit">
                @csrf
                @method('PUT')
                <h2 class="text-lg font-bold text-gray-800 mb-1">Janela do combo</h2>
                <p class="text-sm text-gray-500 mb-6">Quantos meses recentes entram no combo. Cada mês tem 2 períodos, então 6 meses = 12 linhas.</p>

                <label class="block mb-6">
                    <span class="text-sm font-semibold text-gray-700">Meses visíveis *</span>
                    <input type="number" name="meses" min="{{ \App\Services\Tutory\RelatorioPeriodoCatalog::MESES_MIN }}" max="{{ \App\Services\Tutory\RelatorioPeriodoCatalog::MESES_MAX }}" value="{{ old('meses', $mesesVisiveis) }}" required class="mt-2 w-full rounded border border-gray-300 p-3 focus:ring-primary focus:border-primary @error('meses') border-red-500 @enderror">
                </label>

                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-3 bg-primary hover:bg-primary-light text-white rounded font-medium transition">
                        Salvar janela
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="overlay-pdf" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background: rgba(0, 29, 61, 0.72);" role="dialog" aria-modal="true" aria-labelledby="overlay-pdf-titulo">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg mx-4 p-8">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div>
                    <h2 id="overlay-pdf-titulo" class="text-xl font-bold text-gray-800">Gerando PDF</h2>
                    <p id="overlay-pdf-subtitulo" class="text-sm text-gray-500 mt-1">Isso pode levar alguns minutos. Não feche esta página.</p>
                </div>
                <span id="overlay-pdf-timer" class="text-sm font-mono text-gray-600">00:00</span>
            </div>

            <div class="h-2 bg-gray-200 rounded overflow-hidden mb-6">
                <div id="overlay-pdf-barra" class="h-full bg-yellow-600 transition-all duration-500" style="width: 8%;"></div>
            </div>

            <ol id="overlay-pdf-etapas" class="space-y-3 mb-6">
                <li data-step="1" class="flex items-start gap-3 text-sm text-gray-500">
                    <span class="etapa-icone mt-0.5 w-5 h-5 rounded-full border border-gray-300 flex items-center justify-center text-xs">1</span>
                    <span>Conectando à Tutory</span>
                </li>
                <li data-step="2" class="flex items-start gap-3 text-sm text-gray-500">
                    <span class="etapa-icone mt-0.5 w-5 h-5 rounded-full border border-gray-300 flex items-center justify-center text-xs">2</span>
                    <span>Gerando os relatórios-fonte</span>
                </li>
                <li data-step="3" class="flex items-start gap-3 text-sm text-gray-500">
                    <span class="etapa-icone mt-0.5 w-5 h-5 rounded-full border border-gray-300 flex items-center justify-center text-xs">3</span>
                    <span>Montando o PDF consolidado</span>
                </li>
                <li data-step="4" class="flex items-start gap-3 text-sm text-gray-500">
                    <span class="etapa-icone mt-0.5 w-5 h-5 rounded-full border border-gray-300 flex items-center justify-center text-xs">4</span>
                    <span>Preparando o download</span>
                </li>
            </ol>

            <p id="overlay-pdf-log" class="text-xs text-gray-600 bg-gray-50 border border-gray-200 rounded p-3 min-h-[3.5rem]">Aguardando início…</p>
            <p id="overlay-pdf-erro" class="hidden mt-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded p-3"></p>
            <div class="mt-6 flex justify-end">
                <button type="button" id="overlay-pdf-fechar" class="hidden px-5 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded font-medium">Fechar</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('form-pdf-contingencia');
            const overlay = document.getElementById('overlay-pdf');
            const barra = document.getElementById('overlay-pdf-barra');
            const logEl = document.getElementById('overlay-pdf-log');
            const erroEl = document.getElementById('overlay-pdf-erro');
            const timerEl = document.getElementById('overlay-pdf-timer');
            const btn = document.getElementById('btn-gerar-pdf');
            const btnFechar = document.getElementById('overlay-pdf-fechar');
            const progressoUrl = @json(route('relatorios-pdf-contingencia.progresso'));
            const etapas = [8, 28, 55, 82, 100];
            let pollId = null;
            let tickId = null;
            let startedAt = 0;
            let stepAtual = 1;

            function pad(n) { return String(n).padStart(2, '0'); }

            function pintarEtapas(step) {
                stepAtual = Math.max(stepAtual, step);
                document.querySelectorAll('#overlay-pdf-etapas li').forEach(function (li) {
                    const n = parseInt(li.getAttribute('data-step'), 10);
                    const icone = li.querySelector('.etapa-icone');
                    if (n < stepAtual) {
                        li.className = 'flex items-start gap-3 text-sm text-green-700 font-medium';
                        icone.className = 'etapa-icone mt-0.5 w-5 h-5 rounded-full bg-green-600 text-white flex items-center justify-center text-xs';
                        icone.textContent = '✓';
                    } else if (n === stepAtual) {
                        li.className = 'flex items-start gap-3 text-sm text-yellow-800 font-medium';
                        icone.className = 'etapa-icone mt-0.5 w-5 h-5 rounded-full bg-yellow-600 text-white flex items-center justify-center text-xs';
                        icone.textContent = String(n);
                    } else {
                        li.className = 'flex items-start gap-3 text-sm text-gray-500';
                        icone.className = 'etapa-icone mt-0.5 w-5 h-5 rounded-full border border-gray-300 flex items-center justify-center text-xs';
                        icone.textContent = String(n);
                    }
                });
                barra.style.width = etapas[Math.min(stepAtual, 4)] + '%';
            }

            function abrirOverlay() {
                stepAtual = 1;
                erroEl.classList.add('hidden');
                erroEl.textContent = '';
                btnFechar.classList.add('hidden');
                logEl.textContent = 'Conectando à Tutory…';
                pintarEtapas(1);
                overlay.classList.remove('hidden');
                startedAt = Date.now();
                timerEl.textContent = '00:00';
                tickId = setInterval(function () {
                    const s = Math.floor((Date.now() - startedAt) / 1000);
                    timerEl.textContent = pad(Math.floor(s / 60)) + ':' + pad(s % 60);
                    if (stepAtual < 3 && s > 12) pintarEtapas(2);
                    if (stepAtual < 4 && s > 35) pintarEtapas(3);
                }, 250);
            }

            function pararTimers() {
                if (pollId) { clearInterval(pollId); pollId = null; }
                if (tickId) { clearInterval(tickId); tickId = null; }
            }

            function fecharOverlay() {
                pararTimers();
                overlay.classList.add('hidden');
                btn.disabled = false;
            }

            function mostrarErro(msg) {
                pararTimers();
                erroEl.textContent = msg || 'Falha ao gerar o PDF.';
                erroEl.classList.remove('hidden');
                btnFechar.classList.remove('hidden');
                btn.disabled = false;
                barra.style.width = '100%';
                barra.classList.remove('bg-yellow-600');
                barra.classList.add('bg-red-600');
            }

            function nomeArquivo(res) {
                const cd = res.headers.get('Content-Disposition') || '';
                const star = /filename\*=UTF-8''([^;]+)/i.exec(cd);
                if (star) return decodeURIComponent(star[1]);
                const quoted = /filename="([^"]+)"/i.exec(cd);
                if (quoted) return quoted[1];
                const plain = /filename=([^;]+)/i.exec(cd);
                if (plain) return plain[1].trim().replace(/"/g, '');
                return 'relatorio-desempenho.pdf';
            }

            async function baixar(res) {
                const blob = await res.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = nomeArquivo(res);
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(function () { URL.revokeObjectURL(url); }, 1500);
            }

            async function lerErro(res) {
                const tipo = (res.headers.get('Content-Type') || '').toLowerCase();
                if (tipo.indexOf('json') !== -1) {
                    const json = await res.json();
                    return json.message || (json.errors ? Object.values(json.errors).flat()[0] : null) || 'Falha ao gerar o PDF.';
                }
                return 'Falha ao gerar o PDF (HTTP ' + res.status + ').';
            }

            btnFechar.addEventListener('click', fecharOverlay);

            form.addEventListener('submit', async function (ev) {
                ev.preventDefault();
                if (btn.disabled) return;
                btn.disabled = true;
                barra.classList.remove('bg-red-600');
                barra.classList.add('bg-yellow-600');
                abrirOverlay();

                const token = document.getElementById('progress-token').value;
                pollId = setInterval(async function () {
                    try {
                        const r = await fetch(progressoUrl + '?token=' + encodeURIComponent(token), {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        if (!r.ok) return;
                        const data = await r.json();
                        if (data.message) logEl.textContent = data.message;
                        if (data.step) pintarEtapas(parseInt(data.step, 10) || 1);
                        if (data.status === 'error' && data.message) {
                            mostrarErro(data.message);
                        }
                    } catch (e) {}
                }, 900);

                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: {
                            'Accept': 'application/json, application/pdf',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const tipo = (res.headers.get('Content-Type') || '').toLowerCase();
                    if (!res.ok) {
                        mostrarErro(await lerErro(res));
                        return;
                    }
                    if (tipo.indexOf('pdf') === -1 && tipo.indexOf('octet-stream') === -1) {
                        mostrarErro(await lerErro(res));
                        return;
                    }
                    pintarEtapas(4);
                    barra.style.width = '100%';
                    logEl.textContent = 'PDF pronto. Iniciando o download…';
                    await baixar(res);
                    pararTimers();
                    logEl.textContent = 'Download iniciado.';
                    btnFechar.classList.remove('hidden');
                    btn.disabled = false;
                } catch (err) {
                    mostrarErro(err && err.message ? err.message : 'Falha de rede ao gerar o PDF.');
                }
            });
        })();
    </script>
@endsection
