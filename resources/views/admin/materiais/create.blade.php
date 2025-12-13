@extends('layouts.admin')

@section('title', 'Novo Material')

@section('content')
    <div>
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Novo Material</h1>
            <a href="{{ route('materiais.index') }}" class="text-sm text-primary hover:text-primary-light">← Voltar para materiais</a>
        </div>

        @if($errors->any())
            <div class="p-4 mb-4 bg-red-100 text-red-800 rounded border border-red-300">
                <p class="font-bold">Erro ao criar material:</p>
                <ul class="list-disc list-inside mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('materiais.store') }}" method="POST" enctype="multipart/form-data" class="bg-white p-8 rounded shadow-lg max-w-2xl">
            @csrf

            <div class="mb-6">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Título *</span>
                    <input type="text" name="title" required class="mt-2 w-full rounded border border-gray-300 p-3 focus:ring-primary focus:border-primary @error('title') border-red-500 @enderror" value="{{ old('title') }}" placeholder="Ex: Guia Completo PMDF">
                    @error('title') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </label>
            </div>

            <div class="mb-6">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Descrição</span>
                    <textarea name="description" rows="4" class="mt-2 w-full rounded border border-gray-300 p-3 focus:ring-primary focus:border-primary" placeholder="Descreva o conteúdo do material">{{ old('description') }}</textarea>
                </label>
            </div>

            <div class="mb-6">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Link Externo (URL)</span>
                    <input type="url" name="link" class="mt-2 w-full rounded border border-gray-300 p-3 focus:ring-primary focus:border-primary" value="{{ old('link') }}" placeholder="https://exemplo.com">
                </label>
            </div>

            <div class="mb-6">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Arquivo PDF *</span>
                    <input type="file" name="file" accept=".pdf" required class="mt-2 w-full p-3 border border-gray-300 rounded focus:ring-primary focus:border-primary">
                    <p class="text-xs text-gray-500 mt-1">Apenas PDF (máx 10MB)</p>
                </label>
            </div>

            <div class="mb-8">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700 mb-3 block">Associar às Turmas</span>
                    <div class="border border-gray-300 rounded p-4 max-h-60 overflow-y-auto bg-gray-50">
                        @forelse($turmas as $turma)
                            <label class="flex items-center py-2 hover:bg-gray-100 px-2 rounded cursor-pointer">
                                <input type="checkbox" name="turmas[]" value="{{ $turma->id }}" class="mr-3 h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded" {{ in_array($turma->id, old('turmas', [])) ? 'checked' : '' }}>
                                <span class="text-sm text-gray-700">{{ $turma->title }}</span>
                            </label>
                        @empty
                            <p class="text-sm text-gray-500">Nenhuma turma cadastrada</p>
                        @endforelse
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Selecione as turmas relacionadas a este material</p>
                </label>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('materiais.index') }}" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded font-medium transition">Cancelar</a>
                <button type="submit" class="px-6 py-3 bg-primary hover:bg-primary-light text-white rounded font-medium transition">Criar Material</button>
            </div>
        </form>
    </div>
@endsection
