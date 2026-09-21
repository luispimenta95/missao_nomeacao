@php
    use App\Enums\AcaoAcompanhamento;
    use App\Enums\TendenciaFaixa;
    use App\Services\Acompanhamento\TextoAcompanhamento;

    $estilo = match ($linha->ficha->acao) {
        AcaoAcompanhamento::Intervir => 'bg-[#fff1f2] text-[#be123c]',
        AcaoAcompanhamento::MarcarPresenca => 'bg-[#fff7ed] text-[#c2410c]',
        AcaoAcompanhamento::Parabenizar => 'bg-[#ecfdf3] text-[#15803d]',
        AcaoAcompanhamento::EmDia => 'bg-[#f4f7fb] text-[#334155]',
    };
    $temPonte = collect($linha->ficha->motivos)->contains(fn ($motivo) => $motivo->ponteProtocoloResgate);
@endphp

<aside class="fixed inset-y-0 right-0 z-40 flex w-full max-w-[420px] flex-col border-l border-[#e6ebf2] bg-white shadow-2xl">
    <div class="flex items-start justify-between gap-4 border-b border-[#eef2f6] px-5 py-4">
        <div class="flex items-center gap-3">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-[#001d3d] text-sm font-semibold text-white">{{ $linha->iniciais }}</span>
            <div>
                <h2 class="text-lg font-semibold text-[#12263f]">{{ $linha->aluno->nome }}</h2>
                <a href="{{ route('alunos.edit', $linha->aluno) }}" class="text-sm font-medium text-[#1d4ed8] hover:underline">Ver ficha do aluno</a>
            </div>
        </div>
        <a href="{{ $consulta->url(['aluno' => null]) }}" class="rounded-lg p-2 text-[#64748b] hover:bg-[#f4f7fb]" aria-label="Fechar ficha">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </a>
    </div>

    <div class="flex-1 space-y-6 overflow-y-auto px-5 py-5">
        <div class="flex items-center justify-between gap-3">
            <span data-acao-ficha="{{ $linha->ficha->acao->value }}" class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-semibold {{ $estilo }}">
                {{ $linha->ficha->acao->rotulo() }}
            </span>
            <span class="rounded-full bg-[#f4f7fb] px-3 py-1 text-xs font-semibold text-[#475569]">{{ $linha->situacao->rotulo() }}</span>
        </div>

        <section>
            <h3 class="text-sm font-semibold text-[#12263f]">Por quê?</h3>
            <ul class="mt-3 space-y-2">
                @foreach($linha->ficha->motivos as $motivo)
                    <li data-motivo="{{ $motivo->tipo->value }}" class="text-sm text-[#334155]">
                        <span class="mr-2 text-[#94a3b8]">◆</span>{{ $motivo->texto }}
                        @if($motivo->ponteProtocoloResgate)
                            <span class="mt-1 block pl-5 text-xs font-medium text-[#9a3412]">Ponte com o Protocolo de Resgate</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if($temPonte)
                <p class="mt-3 rounded-xl bg-[#fff7ed] px-3 py-2 text-xs leading-5 text-[#9a3412]">
                    Desempenho baixo em assunto é o ponto em que o acompanhamento encontra o Protocolo de Resgate.
                </p>
            @endif
        </section>

        <section>
            <h3 class="text-sm font-semibold text-[#12263f]">Evolução dos parâmetros</h3>
            <div class="mt-3 space-y-4">
                @foreach($linha->ficha->evolucoes as $evolucao)
                    @php
                        $cor = match ($evolucao->tendencia) {
                            TendenciaFaixa::Melhorou => 'text-[#15803d]',
                            TendenciaFaixa::Piorou => 'text-[#be123c]',
                            default => 'text-[#64748b]',
                        };
                    @endphp
                    <div>
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-medium text-[#12263f]">{{ $evolucao->parametro->rotulo() }}</span>
                            <span class="{{ $cor }} font-semibold">{{ $evolucao->tendencia->simbolo() }}</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between gap-3 text-xs text-[#64748b]">
                            <span>Anterior: {{ $evolucao->anterior ?: '—' }}</span>
                            <span>Atual: {{ $evolucao->atual ?: '—' }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        @if($linha->assuntos !== [])
            <section>
                <h3 class="text-sm font-semibold text-[#12263f]">Desempenho por assunto</h3>
                <ul class="mt-3 space-y-2">
                    @foreach(array_slice($linha->assuntos, 0, 4) as $assunto)
                        <li class="flex items-center justify-between gap-3 rounded-xl border border-[#e6ebf2] px-3 py-2">
                            <span class="text-sm text-[#334155]">
                                {{ $assunto['disciplina'] !== '' ? $assunto['disciplina'].' · ' : '' }}{{ $assunto['assunto'] }}
                            </span>
                            <span class="shrink-0 rounded-full bg-[#fff1f2] px-2 py-1 text-xs font-semibold text-[#be123c]">{{ $assunto['faixa_nome'] }}</span>
                        </li>
                    @endforeach
                </ul>
                @if(count($linha->assuntos) > 4)
                    <p class="mt-2 text-xs text-[#64748b]">+ {{ count($linha->assuntos) - 4 }} assuntos na ficha</p>
                @endif
            </section>
        @endif

        <section class="rounded-2xl bg-[#f8fafc] p-4">
            <h3 class="text-sm font-semibold text-[#12263f]">Acompanhamento</h3>
            <dl class="mt-3 space-y-3 text-sm">
                <div>
                    <dt class="text-xs text-[#64748b]">Último contato</dt>
                    <dd class="font-medium text-[#12263f]">{{ $linha->ultimoContato }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-[#64748b]">Última observação</dt>
                    <dd class="text-[#334155]">{{ $linha->aluno->ultima_observacao ?: 'Nenhuma observação' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-[#64748b]">Próximo contato</dt>
                    <dd class="font-medium text-[#12263f]">{{ $linha->proximoContato === '—' ? 'Nenhum agendado' : $linha->proximoContato }}</dd>
                </div>
            </dl>
        </section>

        @if($errors->any())
            <div class="rounded-xl bg-[#fff1f2] px-3 py-2 text-sm text-[#be123c]">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.dashboard.contatos.store', $linha->aluno) }}?{{ http_build_query($consulta->parametros(['aluno' => $linha->aluno->id, 'page' => null])) }}" class="space-y-3">
            @csrf
            <label class="block text-sm font-medium text-[#12263f]">
                Observação do contato
                <textarea name="observacao" rows="3" class="mt-2 w-full rounded-xl border border-[#d7dee8] px-3 py-2 text-sm text-[#12263f] focus:border-[#001d3d] focus:outline-none" placeholder="O que foi combinado neste contato?">{{ old('observacao') }}</textarea>
            </label>
            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#001d3d] px-4 py-3 text-sm font-semibold text-white hover:bg-[#03284f]">
                Registrar contato
            </button>
        </form>

        <form method="POST" action="{{ route('admin.dashboard.agenda.store', $linha->aluno) }}" class="space-y-3">
            @csrf
            <label class="block text-sm font-medium text-[#12263f]">
                Agendar acompanhamento
                <input type="date" name="proximo_contato_em" value="{{ old('proximo_contato_em', optional($linha->aluno->proximo_contato_em)->toDateString()) }}" class="mt-2 w-full rounded-xl border border-[#d7dee8] px-3 py-2 text-sm text-[#12263f] focus:border-[#001d3d] focus:outline-none">
            </label>
            <button type="submit" class="w-full rounded-xl border border-[#d7dee8] px-4 py-3 text-sm font-semibold text-[#12263f] hover:bg-[#f8fafc]">Salvar data</button>
        </form>
        @if($linha->aluno->proximo_contato_em)
            <form method="POST" action="{{ route('admin.dashboard.agenda.store', $linha->aluno) }}">
                @csrf
                <input type="hidden" name="limpar" value="1">
                <button type="submit" class="text-sm font-medium text-[#be123c] hover:underline">Cancelar agendamento</button>
            </form>
        @endif

        <details class="rounded-2xl border border-[#e6ebf2] px-4 py-3">
            <summary class="cursor-pointer text-sm font-semibold text-[#12263f]">Ver histórico de contatos</summary>
            <ul class="mt-3 space-y-3">
                @forelse($contatos as $contato)
                    <li class="border-t border-[#eef2f6] pt-3 text-sm first:border-0 first:pt-0">
                        <p class="text-xs text-[#64748b]">{{ TextoAcompanhamento::dataHora($contato->ocorrido_em) }}</p>
                        <p class="mt-1 text-[#334155]">{{ $contato->observacao ?: 'Contato sem observação' }}</p>
                    </li>
                @empty
                    <li class="text-sm text-[#64748b]">Nenhum contato registrado.</li>
                @endforelse
            </ul>
        </details>
    </div>
</aside>
