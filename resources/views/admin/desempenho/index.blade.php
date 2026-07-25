@extends('layouts.admin')

@section('title', 'Gestão de Desempenho')

@section('content')
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Gestão de Desempenho</h1>
                <p class="text-sm text-gray-600 mt-1">Defina níveis com base nas métricas do Progresso do plano (horas, dias, semanas e % questões).</p>
            </div>
            <a href="{{ route('desempenho.create') }}" class="px-4 py-2 bg-primary hover:bg-primary-light text-white rounded transition">+ Novo nível</a>
        </div>

        @if(session('success'))
            <div class="p-4 bg-green-100 text-green-800 rounded mb-4">{{ session('success') }}</div>
        @endif

        <div class="bg-white rounded shadow p-4 mb-6">
            <h2 class="text-sm font-semibold text-gray-700 mb-2">Critérios disponíveis</h2>
            <div class="flex flex-wrap gap-2">
                @foreach($criterios as $criterio)
                    <span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-700">
                        {{ $criterio->nome }}@if($criterio->unidade) ({{ $criterio->unidade }})@endif
                    </span>
                @endforeach
            </div>
            <p class="text-xs text-gray-500 mt-2">Um nível pode usar 1 ou vários critérios (todos precisam ser atendidos — E lógico). A ordem menor é avaliada primeiro.</p>
        </div>

        <div class="bg-white rounded shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Ordem</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nível</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Critérios</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($niveis as $nivel)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $nivel->ordem }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ $nivel->nome }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $nivel->regras_count }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($nivel->ativo)
                                    <span class="px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">Ativo</span>
                                @else
                                    <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-600">Inativo</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('desempenho.edit', $nivel) }}" class="px-3 py-2 bg-primary hover:bg-primary-light text-white rounded text-sm transition">Editar</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-600">Nenhum nível cadastrado. Crie o primeiro para classificar os alunos no e-mail.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
