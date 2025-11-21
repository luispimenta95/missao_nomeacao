@extends('components.layout')

@section('content')
    <div class="container mx-auto p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Materiais</h1>
        </div>

        @if(session('success'))
            <div class="p-4 bg-green-100 text-green-800 rounded mb-4">{{ session('success') }}</div>
        @endif

        <div class="grid grid-cols-1 gap-4">
            @forelse($materials as $material)
                <div class="p-4 bg-white rounded shadow flex items-center justify-between">
                    <div>
                        <div class="font-semibold">{{ $material->title }}</div>
                        <div class="text-sm text-gray-600">{{ $material->description }}</div>
                    </div>
                    <div class="flex items-center gap-3 justify-end">
                        <a href="{{ route('materiais.download', $material) }}" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded">Download</a>
                        <span class="text-xs text-gray-500">{{ $material->created_at->format('d/m/Y') }}</span>
                    </div>
                </div>
            @empty
                <div class="p-4 bg-white rounded shadow">Nenhum material cadastrado.</div>
            @endforelse
        </div>
    </div>
@endsection
