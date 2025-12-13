@extends('layouts.admin')

@section('title', 'Gerenciar Materiais')

@section('content')
    <div>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Gerenciar Materiais</h1>
            <a href="{{ route('materiais.create') }}" class="px-4 py-2 bg-primary hover:bg-primary-light text-white rounded transition">+ Novo Material</a>
        </div>

        @if(session('success'))
            <div class="p-4 bg-green-100 text-green-800 rounded mb-4">{{ session('success') }}</div>
        @endif

        <div class="grid grid-cols-1 gap-4">
            @forelse($materials as $material)
                <div class="p-4 bg-white rounded shadow flex items-center justify-between hover:shadow-lg transition">
                    <div class="flex-1">
                        <div class="font-semibold text-gray-800">{{ $material->title }}</div>
                        <div class="text-sm text-gray-600">{{ Str::limit($material->description, 100) }}</div>
                        @if($material->link)
                            <div class="text-xs text-primary mt-1">
                                <a href="{{ $material->link }}" target="_blank" class="hover:underline">{{ Str::limit($material->link, 50) }}</a>
                            </div>
                        @endif
                        @if($material->turmas && $material->turmas->count())
                            <div class="flex flex-wrap gap-1 mt-2">
                                @foreach($material->turmas as $turma)
                                    <span class="inline-block px-2 py-1 bg-primary/10 text-primary text-xs rounded font-medium">{{ $turma->title }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('materiais.edit', $material) }}" class="px-3 py-2 bg-primary hover:bg-primary-light text-white rounded text-sm transition">Editar</a>
                    </div>
                </div>
            @empty
                <div class="p-4 bg-white rounded shadow text-center text-gray-600">Nenhum material cadastrado.</div>
            @endforelse
        </div>
    </div>
@endsection
