@extends('layouts.admin')

@section('title', 'Dashboard')

@section('bare')
@php
    use App\Enums\AcaoAcompanhamento;
    use App\Enums\FiltroParametroAcompanhamento;
    use App\Enums\FiltroSituacaoAcompanhamento;
    use App\Enums\FocoAcompanhamento;
@endphp

<div class="min-h-full bg-[#f3f6fb] {{ $aberta ? 'lg:pr-[420px]' : '' }}">
    <div class="mx-auto max-w-[1400px] px-4 py-6 sm:px-6 lg:px-8">
        <header class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight text-[#12263f]">Olá, {{ $primeiroNome }}!</h1>
                <p class="mt-1 text-sm text-[#64748b]">Aqui está o seu panorama de acompanhamento dos alunos.</p>
            </div>
            <div class="flex items-center gap-4">
                <p class="hidden text-sm text-[#64748b] sm:block">{{ $dataExtenso }}</p>
                <a href="{{ $consulta->url(['foco' => FocoAcompanhamento::Intervir->value, 'situacao' => 'pendentes', 'page' => null, 'aluno' => null]) }}" class="relative rounded-full p-2 text-[#12263f] hover:bg-white" aria-label="Intervenções pendentes">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 11-6 0"/></svg>
                    @if($resumo['intervencoes'] > 0)
                        <span class="absolute right-1 top-1 h-2 w-2 rounded-full bg-[#e11d48]"></span>
                    @endif
                </a>
                <div class="flex items-center gap-2 rounded-full bg-white px-2 py-1 shadow-sm">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[#001d3d] text-xs font-semibold text-white">{{ $iniciaisUsuario }}</span>
                    <span class="pr-2 text-sm font-medium text-[#12263f]">{{ auth()->user()->name }}</span>
                </div>
            </div>
        </header>

        @if(session('success'))
            <div class="mb-4 rounded-2xl border border-[#bbf7d0] bg-[#f0fdf4] px-4 py-3 text-sm text-[#166534]">{{ session('success') }}</div>
        @endif

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['valor' => $resumo['ativos'], 'legenda' => 'alunos ativos', 'href' => null, 'icone' => 'users'],
                ['valor' => $resumo['contatados'], 'legenda' => 'contatados nos últimos 15 dias', 'href' => null, 'icone' => 'check'],
                ['valor' => $resumo['sem_contato'], 'legenda' => 'sem contato há mais de 15 dias', 'href' => $consulta->url(['foco' => FocoAcompanhamento::SemContato->value, 'situacao' => 'pendentes', 'page' => null, 'aluno' => null]), 'icone' => 'clock'],
                ['valor' => $resumo['programados'], 'legenda' => 'acompanhamentos programados', 'href' => $consulta->url(['foco' => FocoAcompanhamento::Agenda->value, 'situacao' => 'todas', 'page' => null, 'aluno' => null]), 'icone' => 'calendar'],
            ] as $card)
                @php $tag = $card['href'] ? 'a' : 'div'; @endphp
                <{{ $tag }} @if($card['href']) href="{{ $card['href'] }}" @endif class="flex items-center gap-4 rounded-2xl border border-[#e6ebf2] bg-white px-4 py-4 shadow-sm {{ $card['href'] ? 'hover:border-[#cbd5e1]' : '' }}">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#f4f7fb] text-[#001d3d]">
                        @if($card['icone'] === 'users')
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H2v-2a4 4 0 014-4h3m6-4a4 4 0 11-8 0 4 4 0 018 0zm6 0a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        @elseif($card['icone'] === 'check')
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @elseif($card['icone'] === 'clock')
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @else
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        @endif
                    </span>
                    <span>
                        <span class="block text-2xl font-semibold text-[#12263f]">{{ $card['valor'] }}</span>
                        <span class="block text-sm text-[#64748b]">{{ $card['legenda'] }}</span>
                    </span>
                </{{ $tag }}>
            @endforeach
        </section>

        <section class="mt-6 rounded-3xl border border-[#e6ebf2] bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-[#12263f]">Para hoje</h2>
                    <p class="text-sm text-[#64748b]">O que merece a sua atenção hoje?</p>
                </div>
                <a href="{{ $consulta->url(['foco' => FocoAcompanhamento::Agenda->value, 'situacao' => 'todas', 'page' => null, 'aluno' => null]) }}" class="text-sm font-medium text-[#1d4ed8] hover:underline">Ver agenda completa</a>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach([
                    ['foco' => FocoAcompanhamento::Intervir, 'valor' => $resumo['intervencoes'], 'legenda' => 'intervenções pendentes', 'classes' => 'bg-[#fff1f2] text-[#be123c]', 'situacao' => 'pendentes'],
                    ['foco' => FocoAcompanhamento::AgendaHoje, 'valor' => $resumo['agenda_hoje'], 'legenda' => 'acompanhamento programado para hoje', 'classes' => 'bg-[#fff7ed] text-[#c2410c]', 'situacao' => 'todas'],
                    ['foco' => FocoAcompanhamento::SemContato, 'valor' => $resumo['sem_contato_acao'], 'legenda' => 'alunos sem contato', 'classes' => 'bg-[#eff6ff] text-[#1d4ed8]', 'situacao' => 'pendentes'],
                    ['foco' => FocoAcompanhamento::Parabenizar, 'valor' => $resumo['evolucoes'], 'legenda' => 'evoluções ainda não reconhecidas', 'classes' => 'bg-[#ecfdf3] text-[#15803d]', 'situacao' => 'pendentes'],
                ] as $hojeCard)
                    <a href="{{ $consulta->url(['foco' => $hojeCard['foco']->value, 'situacao' => $hojeCard['situacao'], 'page' => null, 'aluno' => null]) }}" class="rounded-2xl px-4 py-4 {{ $hojeCard['classes'] }} {{ $consulta->foco === $hojeCard['foco'] ? 'ring-2 ring-[#001d3d]' : '' }}">
                        <span class="block text-2xl font-semibold">{{ $hojeCard['valor'] }}</span>
                        <span class="mt-1 block text-sm leading-5">{{ $hojeCard['legenda'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="mt-6 rounded-3xl border border-[#e6ebf2] bg-white shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-[#eef2f6] px-5 py-5">
                <div>
                    <h2 class="text-lg font-semibold text-[#12263f]">Ações de acompanhamento</h2>
                    <p class="mt-1 max-w-2xl text-sm text-[#64748b]">Cada aluno aparece em uma única ação. A ordem é Intervir, Marcar presença e Parabenizar. A ficha reúne todos os motivos.</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 px-5 pt-4">
                @foreach(FiltroSituacaoAcompanhamento::cases() as $situacao)
                    <a href="{{ $consulta->url(['situacao' => $situacao->value, 'page' => null, 'aluno' => null]) }}" class="rounded-full px-4 py-2 text-sm font-semibold {{ $consulta->situacao === $situacao ? 'bg-[#001d3d] text-white' : 'bg-[#f4f7fb] text-[#475569] hover:bg-[#e8eef6]' }}">
                        {{ $situacao->rotulo() }} ({{ $contagens[$situacao->value] }})
                    </a>
                @endforeach
                @if($consulta->foco)
                    <a href="{{ $consulta->url(['foco' => null, 'page' => null, 'aluno' => null]) }}" class="rounded-full bg-[#fff7ed] px-4 py-2 text-sm font-medium text-[#c2410c]">
                        {{ $consulta->foco->rotulo() }} · limpar
                    </a>
                @endif
            </div>

            <form method="GET" action="{{ route('admin.dashboard') }}" class="grid gap-3 px-5 py-4 md:grid-cols-[240px_1fr]">
                <input type="hidden" name="situacao" value="{{ $consulta->situacao->value }}">
                @if($consulta->foco)
                    <input type="hidden" name="foco" value="{{ $consulta->foco->value }}">
                @endif
                <select name="parametro" onchange="this.form.submit()" class="rounded-xl border border-[#d7dee8] bg-white px-3 py-2.5 text-sm text-[#12263f]">
                    <option value="">Todos os parâmetros</option>
                    @foreach(FiltroParametroAcompanhamento::cases() as $parametro)
                        <option value="{{ $parametro->value }}" @selected($consulta->parametro === $parametro)>{{ $parametro->rotulo() }}</option>
                    @endforeach
                </select>
                <label class="relative block">
                    <span class="sr-only">Buscar aluno</span>
                    <input type="search" name="busca" value="{{ $consulta->busca }}" placeholder="Buscar aluno..." class="w-full rounded-xl border border-[#d7dee8] px-3 py-2.5 pl-10 text-sm text-[#12263f] focus:border-[#001d3d] focus:outline-none">
                    <svg class="pointer-events-none absolute left-3 top-3 h-4 w-4 text-[#94a3b8]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.3-4.3M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
                </label>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left">
                    <thead class="bg-[#f8fafc] text-xs font-semibold uppercase tracking-wide text-[#7b8ba0]">
                        <tr>
                            <th class="px-5 py-3">Aluno</th>
                            <th class="px-4 py-3">Ação</th>
                            <th class="px-4 py-3">Motivo principal</th>
                            <th class="px-4 py-3">Último contato</th>
                            <th class="px-4 py-3">Próximo contato</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef2f6]">
                        @forelse($linhas as $linha)
                            @php
                                $estilo = match ($linha->ficha->acao) {
                                    AcaoAcompanhamento::Intervir => 'bg-[#fff1f2] text-[#be123c]',
                                    AcaoAcompanhamento::MarcarPresenca => 'bg-[#fff7ed] text-[#c2410c]',
                                    AcaoAcompanhamento::Parabenizar => 'bg-[#ecfdf3] text-[#15803d]',
                                };
                                $urlLinha = $consulta->url(['aluno' => $linha->aluno->id, 'page' => $paginacao['pagina']]);
                            @endphp
                            <tr data-acao="{{ $linha->ficha->acao->value }}" data-aluno="{{ $linha->aluno->id }}" class="cursor-pointer {{ $aberta && $aberta->aluno->id === $linha->aluno->id ? 'bg-[#f4f7fb]' : 'hover:bg-[#f8fafc]' }}" onclick="window.location='{{ $urlLinha }}'">
                                <td class="px-5 py-4">
                                    <a href="{{ $urlLinha }}" class="flex items-center gap-3">
                                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#e8eef6] text-xs font-semibold text-[#001d3d]">{{ $linha->iniciais }}</span>
                                        <span class="font-medium text-[#12263f]">{{ $linha->aluno->nome }}</span>
                                    </a>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $estilo }}">{{ $linha->ficha->acao->rotulo() }}</span>
                                </td>
                                <td class="max-w-xs px-4 py-4 text-sm text-[#334155]">
                                    {{ $linha->ficha->motivoPrincipal()?->texto }}
                                    @if(count($linha->ficha->motivos) > 1)
                                        <span class="ml-1 text-xs text-[#94a3b8]">+{{ count($linha->ficha->motivos) - 1 }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 text-sm text-[#475569]">{{ $linha->ultimoContato }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-sm {{ $linha->proximoEhHoje ? 'font-semibold text-[#c2410c]' : 'text-[#475569]' }}">{{ $linha->proximoContato }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center text-sm text-[#64748b]">
                                    Nenhum aluno nesta lista. A ação aparece quando a constância ou o volume ficam críticos, o desempenho fica baixo, ou o contato passa de 15 dias.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between gap-3 border-t border-[#eef2f6] px-5 py-4 text-sm text-[#64748b]">
                <p>Mostrando {{ $paginacao['de'] }}–{{ $paginacao['ate'] }} de {{ $paginacao['total'] }} alunos</p>
                <div class="flex items-center gap-2">
                    @if($paginacao['pagina'] > 1)
                        <a href="{{ $consulta->url(['page' => $paginacao['pagina'] - 1, 'aluno' => null]) }}" class="rounded-lg border border-[#d7dee8] px-3 py-1.5 hover:bg-[#f8fafc]">Anterior</a>
                    @endif
                    <span class="rounded-lg bg-[#001d3d] px-3 py-1.5 font-semibold text-white">{{ $paginacao['pagina'] }}</span>
                    @if($paginacao['pagina'] < $paginacao['paginas'])
                        <a href="{{ $consulta->url(['page' => $paginacao['pagina'] + 1, 'aluno' => null]) }}" class="rounded-lg border border-[#d7dee8] px-3 py-1.5 hover:bg-[#f8fafc]">Próxima</a>
                    @endif
                </div>
            </div>
        </section>
    </div>
</div>

@if($aberta)
    <a href="{{ $consulta->url(['aluno' => null]) }}" class="fixed inset-0 z-30 bg-[#0f172a]/30 lg:hidden" aria-label="Fechar ficha"></a>
    @include('admin.dashboard.ficha', ['linha' => $aberta, 'consulta' => $consulta, 'contatos' => $contatos])
@endif
@endsection
