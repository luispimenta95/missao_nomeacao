@extends('layouts.admin')

@section('title', 'Editar Material')

@section('content')
    <div>
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Editar Material</h1>
            <a href="{{ route('materiais.index') }}" class="text-sm text-primary hover:text-primary-light">← Voltar para materiais</a>
        </div>

        @if($errors->any())
            <div class="p-4 mb-4 bg-red-100 text-red-800 rounded border border-red-300">
                <p class="font-bold">Erro ao editar material:</p>
                <ul class="list-disc list-inside mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('materiais.update', $material) }}" method="POST" enctype="multipart/form-data" class="bg-white p-8 rounded shadow-lg max-w-2xl">
            @csrf
            @method('PUT')

            <div class="mb-6">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Título *</span>
                    <input type="text" name="title" required class="mt-2 w-full rounded border border-gray-300 p-3 focus:ring-primary focus:border-primary @error('title') border-red-500 @enderror" value="{{ old('title', $material->title) }}">
                    @error('title') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </label>
            </div>

            <div class="mb-6">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Descrição</span>
                    <textarea name="description" rows="4" class="mt-2 w-full rounded border border-gray-300 p-3 focus:ring-primary focus:border-primary">{{ old('description', $material->description) }}</textarea>
                </label>
            </div>

            <div class="mb-6">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Link Externo (URL)</span>
                    <input type="url" name="link" class="mt-2 w-full rounded border border-gray-300 p-3 focus:ring-primary focus:border-primary" value="{{ old('link', $material->link) }}">
                </label>
            </div>

            <div class="mb-6">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Arquivo PDF</span>
                    @if($material->file_path)
                        <p class="text-sm text-gray-600 mt-2 mb-2">Arquivo atual: <strong>{{ basename($material->file_path) }}</strong></p>
                    @endif
                    <input type="file" name="file" accept=".pdf" class="mt-2 w-full p-3 border border-gray-300 rounded focus:ring-primary focus:border-primary">
                    <p class="text-xs text-gray-500 mt-1">Apenas PDF (máx 10MB) - Deixar vazio para manter arquivo atual</p>
                </label>
            </div>

            <div class="mb-8">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700 mb-3 block">Associar às Turmas</span>
                    <div class="border border-gray-300 rounded p-4 max-h-60 overflow-y-auto bg-gray-50">
                        @forelse($turmas as $turma)
                            <label class="flex items-center py-2 hover:bg-gray-100 px-2 rounded cursor-pointer">
                                <input type="checkbox" name="turmas[]" value="{{ $turma->id }}" class="mr-3 h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded" {{ in_array($turma->id, old('turmas', $material->turmas->pluck('id')->toArray())) ? 'checked' : '' }}>
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
                <button type="submit" class="px-6 py-3 bg-primary hover:bg-primary-light text-white rounded font-medium transition">Atualizar Material</button>
            </div>
        </form>

        <!-- Danger Zone -->
        <div class="mt-8 p-6 bg-red-50 border border-red-200 rounded-lg max-w-2xl">
            <h3 class="text-lg font-bold text-red-800 mb-2">Zona de Perigo</h3>
            <p class="text-sm text-red-700 mb-4">Uma vez deletado, o material não pode ser recuperado.</p>
            <form action="{{ route('materiais.destroy', $material) }}" method="POST" onsubmit="return confirm('Tem certeza que deseja deletar este material?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded font-medium transition">Deletar Material</button>
            </form>
        </div>
    </div>
@endsection
