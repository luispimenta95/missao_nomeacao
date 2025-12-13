@extends('layouts.admin')

@section('title', 'Gerenciar Turmas')

@section('content')
    <div>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Gerenciar Turmas</h1>
            <a href="{{ route('turmas.create') }}" class="px-4 py-2 bg-primary hover:bg-primary-light text-white rounded transition">+ Nova Turma</a>
        </div>

        @if(session('success'))
            <div class="p-4 bg-green-100 text-green-800 rounded mb-4">{{ session('success') }}</div>
        @endif

        <div class="grid grid-cols-1 gap-4">
            @forelse($turmas as $turma)
                <div class="p-4 bg-white rounded shadow flex items-center justify-between hover:shadow-lg transition">
                    <div class="flex items-center gap-4 flex-1">
                        @if($turma->logo_path)
                            <img src="{{ asset('storage/' . $turma->logo_path) }}" alt="{{ $turma->title }}" class="h-12 w-12 object-cover rounded">
                        @else
                            <div class="h-12 w-12 bg-gray-200 rounded flex items-center justify-center">
                                <span class="text-xs text-gray-500">Logo</span>
                            </div>
                        @endif
                        <div class="flex-1">
                            <div class="font-semibold text-gray-800">{{ $turma->title }}</div>
                            <div class="text-sm text-gray-600">{{ Str::limit($turma->description, 100) }}</div>
                            <div class="text-xs text-gray-500 mt-1">Vagas: {{ $turma->available_slots ?? 'Ilimitadas' }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 justify-end">
                        <span class="px-3 py-1 rounded text-sm font-medium
                            @if($turma->status === 'aberta') bg-green-100 text-green-800
                            @elseif($turma->status === 'fechada') bg-red-100 text-red-800
                            @else bg-yellow-100 text-yellow-800
                            @endif">
                            {{ ucfirst($turma->status) }}
                        </span>
                        <a href="{{ route('turmas.edit', $turma) }}" class="px-3 py-2 bg-primary hover:bg-primary-light text-white rounded text-sm transition">Editar</a>
                    </div>
                </div>
            @empty
                <div class="p-4 bg-white rounded shadow text-center text-gray-600">Nenhuma turma cadastrada.</div>
            @endforelse
        </div>
    </div>
@endsection
