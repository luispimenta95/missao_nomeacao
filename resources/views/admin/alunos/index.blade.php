@extends('layouts.admin')

@section('title', 'Gerenciar Alunos')

@section('content')
    <div>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Gerenciar Alunos</h1>
            <a href="{{ route('alunos.create') }}" class="px-4 py-2 bg-primary hover:bg-primary-light text-white rounded transition">+ Novo Aluno</a>
        </div>

        @if(session('success'))
            <div class="p-4 bg-green-100 text-green-800 rounded mb-4">{{ session('success') }}</div>
        @endif

        <div class="bg-white rounded shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ID</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nome</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">E-mail</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Recebe e-mail</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($alunos as $aluno)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $aluno->id }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ $aluno->nome }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $aluno->email }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if($aluno->recebe_email)
                                    <span class="px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">Sim</span>
                                @else
                                    <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-600">Não</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('alunos.edit', $aluno) }}" class="px-3 py-2 bg-primary hover:bg-primary-light text-white rounded text-sm transition">Editar</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-600">Nenhum aluno cadastrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
