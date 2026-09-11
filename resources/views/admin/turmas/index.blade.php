@extends('layouts.admin')

@section('title', 'Gerenciar Turmas')

@section('content')
    <div>
        <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Gerenciar Turmas</h1>
                <p class="text-sm text-gray-600 mt-1 max-w-3xl">
                    Cadastro interno das preparações. Ativo, visibilidade, estágio e CTAs definidos aqui alimentam
                    <strong>/turmas-abertas</strong> e <strong>/mentoria</strong> no site Missão Nomeação.
                </p>
            </div>
            <a href="{{ route('turmas.create') }}" class="px-4 py-2 bg-primary hover:bg-primary-light text-white rounded transition">+ Nova Turma</a>
        </div>

        @if(session('success'))
            <div class="p-4 bg-green-100 text-green-800 rounded mb-4">{{ session('success') }}</div>
        @endif

        <div class="bg-white rounded shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Preparação</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Grupo</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Estágio</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Site</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Ordem</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($turmas as $turma)
                        <tr class="hover:bg-gray-50 {{ $turma->ativo ? '' : 'opacity-60' }}">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    @if($turma->logo_path)
                                        <img src="{{ asset('storage/' . $turma->logo_path) }}" alt="{{ $turma->nomePublicoExibido() }}" class="h-10 w-10 object-cover rounded">
                                    @else
                                        <div class="h-10 w-10 bg-gray-200 rounded flex items-center justify-center text-[10px] text-gray-500">Capa</div>
                                    @endif
                                    <div>
                                        <div class="font-semibold text-gray-800">{{ $turma->title }}</div>
                                        <div class="text-xs text-gray-500">{{ $turma->nomePublicoExibido() }} · {{ $turma->slug }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                {{ \App\Models\Turma::GRUPOS_EXIBICAO[$turma->grupo_exibicao] ?? $turma->grupo_exibicao }}
                                @if($turma->categoria_navegacao)
                                    <div class="text-xs text-gray-500">{{ $turma->categoria_navegacao }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    @if($turma->estagio() === 'inscricoes_abertas') bg-green-100 text-green-800
                                    @elseif($turma->estagio() === 'lista_interesse') bg-blue-100 text-blue-800
                                    @else bg-yellow-100 text-yellow-800
                                    @endif">
                                    {{ $turma->badgePublico() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600 space-y-1">
                                <div>{{ $turma->ativo ? 'Ativa' : 'Arquivada' }}</div>
                                <div>{{ $turma->exibir_no_site ? 'Visível no site' : 'Oculta no site' }}</div>
                                @if($turma->exibir_na_mentoria)
                                    <div>Também na /mentoria</div>
                                @endif
                                @if($turma->destacar_turmas_abertas)
                                    <div>Destacada</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $turma->ordem_exibicao }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('turmas.edit', $turma) }}" class="px-3 py-2 bg-primary hover:bg-primary-light text-white rounded text-sm transition">Editar</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-600">Nenhuma turma cadastrada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
