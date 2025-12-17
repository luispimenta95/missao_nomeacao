@extends('layouts.admin')

@section('title', 'Inscrições')

@section('content')
    <div>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Inscrições em Turmas</h1>
            <a href="{{ route('inscricoes.export') }}" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded transition">↓ Exportar CSV</a>
        </div>

        @if(session('success'))
            <div class="p-4 bg-green-100 text-green-800 rounded mb-4">{{ session('success') }}</div>
        @endif

        <!-- Filtro de Data -->
        <div class="mb-6 bg-white p-4 rounded shadow">
            <form method="GET" action="{{ route('inscricoes.index') }}" class="flex items-end gap-4">
                <div class="flex-1">
                    <label for="date" class="block text-sm font-medium text-gray-700 mb-2">Filtrar por Mês/Ano</label>
                    <input 
                        type="month" 
                        id="date" 
                        name="date" 
                        value="{{ $selectedDate }}" 
                        max="{{ date('Y-m') }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                </div>
                <button type="submit" class="px-4 py-2 bg-primary hover:bg-primary-light text-white rounded transition">Filtrar</button>
                @if($selectedDate)
                    <a href="{{ route('inscricoes.index') }}" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded transition">Limpar</a>
                @endif
            </form>
        </div>

        <!-- Card: Turma com Mais Inscritos -->
        @if($turmaComMaisInscritos)
            <div class="mb-6 p-6 bg-gradient-to-r from-primary to-black rounded shadow-lg text-white">
                <p class="text-sm font-semibold opacity-90">Turma com Mais Inscritos</p>
                <div class="flex items-center justify-between mt-2">
                    <div>
                        <h3 class="text-2xl font-bold">{{ $turmaComMaisInscritos->title }}</h3>
                        <p class="text-sm opacity-80 mt-1">{{ $turmaComMaisInscritos->inscricoes_count }} inscricao(ões)</p>
                    </div>
                    <a href="{{ route('turmas.edit', $turmaComMaisInscritos) }}" class="px-4 py-2 bg-white text-primary rounded font-semibold hover:bg-gray-100 transition">Ver Turma</a>
                </div>
            </div>
        @endif

        <div class="bg-white rounded shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-primary text-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Nome</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Email</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Telefone</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Turma</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold">Data da Inscrição</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($inscricoes as $inscricao)
                            <tr class="border-t border-gray-200 hover:bg-gray-50 transition">
                                <td class="px-6 py-4 text-sm text-gray-800 font-medium">{{ $inscricao->name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <a href="mailto:{{ $inscricao->email }}" class="text-primary hover:underline">{{ $inscricao->email }}</a>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ $inscricao->phone }}</td>
                                <td class="px-6 py-4 text-sm text-gray-800 font-medium">
                                    <a href="{{ route('turmas.edit', $inscricao->turma) }}" class="text-primary hover:underline">
                                        {{ $inscricao->turma->title }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ $inscricao->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-600">Nenhuma inscrição cadastrada</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($inscricoes->hasPages())
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                    {{ $inscricoes->links() }}
                </div>
            @endif
        </div>

        <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white p-6 rounded shadow">
                <p class="text-gray-600 text-sm">Total de Inscrições</p>
                <p class="text-3xl font-bold text-primary">{{ $totalInscricoes }}</p>
            </div>
            <div class="bg-white p-6 rounded shadow">
                <p class="text-gray-600 text-sm">Este Mês</p>
                <p class="text-3xl font-bold text-red-600">{{ $inscricoesThisMonth }}</p>
            </div>
            <div class="bg-white p-6 rounded shadow">
                <p class="text-gray-600 text-sm">Turmas Ativas</p>
                <p class="text-3xl font-bold text-green-600">{{ $activeturmas }}</p>
            </div>
        </div>
    </div>
@endsection
