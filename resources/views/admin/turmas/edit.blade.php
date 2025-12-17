@extends('layouts.admin')

@section('title', 'Editar Turma')

@section('content')
    <div>
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Editar Turma</h1>
            <a href="{{ route('turmas.index') }}" class="text-sm text-primary hover:text-primary-light">← Voltar para turmas</a>
        </div>

        @if($errors->any())
            <div class="p-4 mb-4 bg-red-100 text-red-800 rounded border border-red-300">
                <p class="font-bold">Erro ao editar turma:</p>
                <ul class="list-disc list-inside mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('turmas.update', $turma) }}" method="POST" enctype="multipart/form-data" class="bg-white p-8 rounded shadow-lg max-w-2xl">
            @csrf
            @method('PUT')

            <div class="mb-6">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Título da Turma *</span>
                    <input type="text" name="title" required class="mt-2 w-full rounded border border-gray-300 p-3 focus:ring-primary focus:border-primary @error('title') border-red-500 @enderror" value="{{ old('title', $turma->title) }}">
                    @error('title') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </label>
            </div>

            <div class="mb-6">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Descrição</span>
                    <textarea name="description" rows="4" class="mt-2 w-full rounded border border-gray-300 p-3 focus:ring-primary focus:border-primary">{{ old('description', $turma->description) }}</textarea>
                </label>
            </div>

            <div class="mb-6">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Logo da Turma</span>
                    @if($turma->logo_path)
                        <div class="mb-4">
                            <p class="text-sm text-gray-600 mb-2">Logo atual:</p>
                            <img src="{{ asset('storage/' . $turma->logo_path) }}" alt="{{ $turma->title }}" class="h-24 w-24 object-cover rounded border border-gray-200">
                        </div>
                    @endif
                    <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml" class="mt-2 w-full p-3 border border-gray-300 rounded focus:ring-primary focus:border-primary">
                    <p class="text-xs text-gray-500 mt-1">PNG, JPG ou SVG (máx 5MB) - Deixar vazio para manter logo atual</p>
                </label>
            </div>

            <div class="mb-6">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">URL de Checkout *</span>
                    <input type="url" name="checkout_url" required class="mt-2 w-full rounded border border-gray-300 p-3 focus:ring-primary focus:border-primary @error('checkout_url') border-red-500 @enderror" value="{{ old('checkout_url', $turma->checkout_url) }}">
                    @error('checkout_url') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </label>
            </div>

            <div class="mb-6">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Data de Início</span>
                    <input type="date" name="start_date" class="mt-2 w-full rounded border border-gray-300 p-3 focus:ring-primary focus:border-primary" value="{{ old('start_date', $turma->start_date?->format('Y-m-d')) }}">
                </label>
            </div>

            <div class="mb-6">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Vagas Disponíveis</span>
                    <input type="number" name="available_slots" min="0" class="mt-2 w-full rounded border border-gray-300 p-3 focus:ring-primary focus:border-primary" value="{{ old('available_slots', $turma->available_slots) }}">
                </label>
            </div>

            <div class="mb-8">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Status</span>
                    <select name="status" required class="mt-2 w-full rounded border border-gray-300 p-3 focus:ring-primary focus:border-primary">
                        <option value="aberta" {{ old('status', $turma->status) === 'aberta' ? 'selected' : '' }}>Aberta</option>
                        <option value="fechada" {{ old('status', $turma->status) === 'fechada' ? 'selected' : '' }}>Fechada</option>
                        <option value="completa" {{ old('status', $turma->status) === 'completa' ? 'selected' : '' }}>Completa</option>
                    </select>
                </label>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('turmas.index') }}" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded font-medium transition">Cancelar</a>
                <button type="submit" class="px-6 py-3 bg-primary hover:bg-primary-light text-white rounded font-medium transition">Atualizar Turma</button>
            </div>
        </form>

        <!-- Danger Zone -->
        <div class="mt-8 p-6 bg-red-50 border border-red-200 rounded-lg max-w-2xl">
            <h3 class="text-lg font-bold text-red-800 mb-2">Zona de Perigo</h3>
            <p class="text-sm text-red-700 mb-4">Uma vez deletada, a turma não pode ser recuperada.</p>
            <form action="{{ route('turmas.destroy', $turma) }}" method="POST" onsubmit="return confirm('Tem certeza que deseja deletar esta turma?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded font-medium transition">Deletar Turma</button>
            </form>
        </div>
    </div>
@endsection
