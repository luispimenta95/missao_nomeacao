@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
@php
use App\Enums\AcaoAcompanhamento;
use App\Enums\FiltroParametroAcompanhamento;
use App\Enums\FiltroSituacaoAcompanhamento;
use App\Enums\FocoAcompanhamento;
@endphp

<div class="{{ $aberta ? 'lg:pr-[420px]' : '' }}">
    <header class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Olá, {{ $primeiroNome }}!</h1>
            <p class="mt-1 text-sm text-gray-600">Aqui está o seu panorama de acompanhamento dos alunos.</p>
        </div>
        <div class="flex items-center gap-4">
            <p class="hidden text-sm text-gray-600 sm:block">{{ $dataExtenso }}</p>
            <a href="{{ $consulta->url(['foco' => FocoAcompanhamento::Intervir->value, 'situacao' => 'pendentes', 'page' => null, 'aluno' => null]) }}" class="relative rounded p-2 text-gray-800 hover:bg-gray-100" aria-label="Intervenções pendentes">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 11-6 0" />
                </svg>
                @if($resumo['intervencoes'] > 0)
                <span class="absolute right-1 top-1 h-2 w-2 rounded-full bg-red-600"></span>
                @endif
            </a>
            <div class="flex items-center gap-2 rounded bg-white px-2 py-1 shadow">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-xs font-semibold text-white">{{ $iniciaisUsuario }}</span>
                <span class="pr-2 text-sm font-medium text-gray-800">{{ auth()->user()->name }}</span>
            </div>
        </div>
    </header>

    @if(session('success'))
    <div class="mb-4 rounded bg-green-100 p-4 text-green-800">{{ session('success') }}</div>
    @endif

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
        ['valor' => $resumo['ativos'], 'legenda' => 'alunos ativos', 'href' => null, 'icone' => 'users'],
        ['valor' => $resumo['contatados'], 'legenda' => 'contatados nos últimos 15 dias', 'href' => null, 'icone' => 'check'],
        ['valor' => $resumo['sem_contato'], 'legenda' => 'sem contato há mais de 15 dias', 'href' => $consulta->url(['foco' => FocoAcompanhamento::SemContato->value, 'situacao' => 'pendentes', 'page' => null, 'aluno' => null]), 'icone' => 'clock'],
        ['valor' => $resumo['programados'], 'legenda' => 'acompanhamentos programados', 'href' => $consulta->url(['foco' => FocoAcompanhamento::Agenda->value, 'situacao' => 'todas', 'page' => null, 'aluno' => null]), 'icone' => 'calendar'],
        ] as $card)
        @php $tag = $card['href'] ? 'a' : 'div'; @endphp
        <{{ $tag }} @if($card['href']) href="{{ $card['href'] }}" @endif class="flex items-center gap-4 rounded bg-white px-4 py-4 shadow {{ $card['href'] ? 'transition hover:shadow-lg' : '' }}">
            <span class="flex h-11 w-11 items-center justify-center rounded bg-primary/10 text-primary">
                @if($card['icone'] === 'users')
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H2v-2a4 4 0 014-4h3m6-4a4 4 0 11-8 0 4 4 0 018 0zm6 0a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                @elseif($card['icone'] === 'check')
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                @elseif($card['icone'] === 'clock')
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                @else
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                @endif
            </span>
            <span>
                <span class="block text-2xl font-bold text-primary">{{ $card['valor'] }}</span>
                <span class="block text-sm text-gray-600">{{ $card['legenda'] }}</span>
            </span>
        </{{ $tag }}>
        @endforeach
    </section>

    <section class="mt-6 rounded bg-white p-5 shadow">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-gray-800">Para hoje</h2>
                <p class="text-sm text-gray-600">O que merece a sua atenção hoje?</p>
            </div>
            <a href="{{ $consulta->url(['foco' => FocoAcompanhamento::Agenda->value, 'situacao' => 'todas', 'page' => null, 'aluno' => null]) }}" class="text-sm font-medium text-primary hover:text-primary-light">Ver agenda completa</a>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
            ['foco' => FocoAcompanhamento::Intervir, 'valor' => $resumo['intervencoes'], 'legenda' => 'intervenções pendentes', 'classes' => 'bg-red-100 text-red-800', 'situacao' => 'pendentes'],
            ['foco' => FocoAcompanhamento::AgendaHoje, 'valor' => $resumo['agenda_hoje'], 'legenda' => 'acompanhamento programado para hoje', 'classes' => 'bg-primary/10 text-primary', 'situacao' => 'todas'],
            ['foco' => FocoAcompanhamento::SemContato, 'valor' => $resumo['sem_contato_acao'], 'legenda' => 'alunos sem contato', 'classes' => 'bg-gray-100 text-gray-800', 'situacao' => 'pendentes'],
            ['foco' => FocoAcompanhamento::Parabenizar, 'valor' => $resumo['evolucoes'], 'legenda' => 'evoluções ainda não reconhecidas', 'classes' => 'bg-green-100 text-green-800', 'situacao' => 'pendentes'],
            ] as $hojeCard)
            <a href="{{ $consulta->url(['foco' => $hojeCard['foco']->value, 'situacao' => $hojeCard['situacao'], 'page' => null, 'aluno' => null]) }}" class="rounded px-4 py-4 {{ $hojeCard['classes'] }} {{ $consulta->foco === $hojeCard['foco'] ? 'ring-2 ring-primary-light' : '' }}">
                <span class="block text-2xl font-bold">{{ $hojeCard['valor'] }}</span>
                <span class="mt-1 block text-sm leading-5">{{ $hojeCard['legenda'] }}</span>
            </a>
            @endforeach
        </div>
    </section>

    <section class="mt-6 rounded bg-white shadow">

        <div class="flex flex-wrap items-center gap-2 px-5 pt-4">
            @foreach(FiltroSituacaoAcompanhamento::cases() as $situacao)
            <a href="{{ $consulta->url(['situacao' => $situacao->value, 'page' => null, 'aluno' => null]) }}" class="rounded px-4 py-2 text-sm font-medium transition {{ $consulta->situacao === $situacao ? 'bg-primary text-white' : 'bg-gray-200 text-gray-800 hover:bg-gray-300' }}">
                {{ $situacao->rotulo() }} ({{ $contagens[$situacao->value] }})
            </a>
            @endforeach
            @if($consulta->foco)
            <a href="{{ $consulta->url(['foco' => null, 'page' => null, 'aluno' => null]) }}" class="rounded bg-primary/10 px-4 py-2 text-sm font-medium text-primary">
                {{ $consulta->foco->rotulo() }} · limpar
            </a>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2 px-5 pt-3">
            <a href="{{ $consulta->url(['acao' => null, 'page' => null, 'aluno' => null]) }}" class="rounded px-4 py-2 text-sm font-medium transition {{ $consulta->acao === null ? 'bg-primary-light text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                Todas as ações ({{ $contagens[$consulta->situacao->value] }})
            </a>
            @foreach(AcaoAcompanhamento::cases() as $acao)
            @php
            $chip = match ($acao) {
            AcaoAcompanhamento::Intervir => 'bg-red-100 text-red-800',
            AcaoAcompanhamento::MarcarPresenca => 'bg-primary/10 text-primary',
            AcaoAcompanhamento::Parabenizar => 'bg-green-100 text-green-800',
            AcaoAcompanhamento::EmDia => 'bg-gray-100 text-gray-700',
            };
            @endphp
            <a href="{{ $consulta->url(['acao' => $acao->value, 'page' => null, 'aluno' => null]) }}" class="rounded px-4 py-2 text-sm font-medium {{ $consulta->acao === $acao ? 'ring-2 ring-primary-light '.$chip : $chip }}">
                {{ $acao->rotulo() }} ({{ $contagens[$acao->value] }})
            </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.dashboard') }}" class="grid gap-3 px-5 py-4 md:grid-cols-[240px_1fr]">
            <input type="hidden" name="situacao" value="{{ $consulta->situacao->value }}">
            @if($consulta->foco)
            <input type="hidden" name="foco" value="{{ $consulta->foco->value }}">
            @endif
            @if($consulta->acao)
            <input type="hidden" name="acao" value="{{ $consulta->acao->value }}">
            @endif
            <select name="parametro" onchange="this.form.submit()" class="rounded border border-gray-300 bg-white px-3 py-3 text-sm text-gray-800 focus:border-primary focus:ring-primary">
                <option value="">Todos os parâmetros</option>
                @foreach(FiltroParametroAcompanhamento::cases() as $parametro)
                <option value="{{ $parametro->value }}" @selected($consulta->parametro === $parametro)>{{ $parametro->rotulo() }}</option>
                @endforeach
            </select>
            <label class="relative block">
                <span class="sr-only">Buscar aluno</span>
                <input type="search" name="busca" value="{{ $consulta->busca }}" placeholder="Buscar aluno..." class="w-full rounded border border-gray-300 px-3 py-3 pl-10 text-sm text-gray-800 focus:border-primary focus:ring-primary">
                <svg class="pointer-events-none absolute left-3 top-3.5 h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.3-4.3M11 18a7 7 0 100-14 7 7 0 000 14z" />
                </svg>
            </label>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-600">
                    <tr>
                        <th class="px-4 py-3">Aluno</th>
                        <th class="px-4 py-3">Ação</th>
                        <th class="px-4 py-3">Motivo principal</th>
                        <th class="px-4 py-3">Último contato</th>
                        <th class="px-4 py-3">Próximo contato</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($linhas as $linha)
                    @php
                    $estilo = match ($linha->ficha->acao) {
                    AcaoAcompanhamento::Intervir => 'bg-red-100 text-red-800',
                    AcaoAcompanhamento::MarcarPresenca => 'bg-primary/10 text-primary',
                    AcaoAcompanhamento::Parabenizar => 'bg-green-100 text-green-800',
                    AcaoAcompanhamento::EmDia => 'bg-gray-100 text-gray-700',
                    };
                    $urlLinha = $consulta->url(['aluno' => $linha->aluno->id, 'page' => $paginacao['pagina']]);
                    @endphp
                    <tr data-acao="{{ $linha->ficha->acao->value }}" data-aluno="{{ $linha->aluno->id }}" class="cursor-pointer {{ $aberta && $aberta->aluno->id === $linha->aluno->id ? 'bg-gray-50' : 'hover:bg-gray-50' }}" onclick="window.location='{{ $urlLinha }}'">
                        <td class="px-4 py-4">
                            <a href="{{ $urlLinha }}" class="flex items-center gap-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">{{ $linha->iniciais }}</span>
                                <span class="font-semibold text-gray-800">{{ $linha->aluno->nome }}</span>
                            </a>
                        </td>
                        <td class="px-4 py-4">
                            <span class="inline-flex rounded px-2 py-1 text-xs font-medium {{ $estilo }}">{{ $linha->ficha->acao->rotulo() }}</span>
                        </td>
                        <td class="max-w-xs px-4 py-4 text-sm text-gray-700">
                            {{ $linha->ficha->motivoPrincipal()?->texto }}
                            @if(count($linha->ficha->motivos) > 1)
                            <span class="ml-1 text-xs text-gray-500">+{{ count($linha->ficha->motivos) - 1 }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-600">{{ $linha->ultimoContato }}</td>
                        <td class="whitespace-nowrap px-4 py-4 text-sm {{ $linha->proximoEhHoje ? 'font-semibold text-primary-light' : 'text-gray-600' }}">{{ $linha->proximoContato }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-sm text-gray-600">
                            Nenhum aluno nesta lista.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between gap-3 border-t border-gray-100 px-5 py-4 text-sm text-gray-600">
            <p>Mostrando {{ $paginacao['de'] }}–{{ $paginacao['ate'] }} de {{ $paginacao['total'] }} alunos</p>
            <div class="flex items-center gap-2">
                @if($paginacao['pagina'] > 1)
                <a href="{{ $consulta->url(['page' => $paginacao['pagina'] - 1, 'aluno' => null]) }}" class="rounded bg-gray-200 px-3 py-2 font-medium text-gray-800 transition hover:bg-gray-300">Anterior</a>
                @endif
                <span class="rounded bg-primary px-3 py-2 font-medium text-white">{{ $paginacao['pagina'] }}</span>
                @if($paginacao['pagina'] < $paginacao['paginas'])
                    <a href="{{ $consulta->url(['page' => $paginacao['pagina'] + 1, 'aluno' => null]) }}" class="rounded bg-gray-200 px-3 py-2 font-medium text-gray-800 transition hover:bg-gray-300">Próxima</a>
                    @endif
            </div>
        </div>
    </section>
</div>

@if($aberta)
<a href="{{ $consulta->url(['aluno' => null]) }}" class="fixed inset-0 z-30 bg-black/40 lg:hidden" aria-label="Fechar ficha"></a>
@include('admin.dashboard.ficha', ['linha' => $aberta, 'consulta' => $consulta, 'contatos' => $contatos])
@endif
@endsection