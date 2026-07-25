@extends('layouts.admin')

@section('title', 'Editar faixa de desempenho')

@section('content')
    <div>
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Editar faixa</h1>
            <p class="text-sm text-gray-600 mt-1">
                Eixo: <strong>{{ $faixa->eixo->nome }}</strong> · Código: <code>{{ $faixa->codigo }}</code>
            </p>
            <a href="{{ route('desempenho.index') }}" class="text-sm text-primary hover:text-primary-light">← Voltar</a>
        </div>

        @if($errors->any())
            <div class="p-4 mb-4 bg-red-100 text-red-800 rounded border border-red-300">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('desempenho.update', $faixa) }}" method="POST" class="bg-white p-8 rounded shadow-lg max-w-3xl">
            @csrf
            @method('PUT')

            <div class="mb-6">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Nome da faixa *</span>
                    <input type="text" name="nome" required class="mt-2 w-full rounded border border-gray-300 p-3" value="{{ old('nome', $faixa->nome) }}">
                </label>
            </div>

            <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Valor mínimo</span>
                    <input type="number" step="0.01" name="valor_min" class="mt-2 w-full rounded border border-gray-300 p-3" value="{{ old('valor_min', $faixa->valor_min) }}">
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Valor máximo</span>
                    <input type="number" step="0.01" name="valor_max" class="mt-2 w-full rounded border border-gray-300 p-3" value="{{ old('valor_max', $faixa->valor_max) }}">
                    <span class="text-xs text-gray-500">Deixe vazio para “sem limite superior”.</span>
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Ordem *</span>
                    <input type="number" min="1" name="ordem" required class="mt-2 w-full rounded border border-gray-300 p-3" value="{{ old('ordem', $faixa->ordem) }}">
                </label>
            </div>

            <div class="mb-6">
                <label class="inline-flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="ativo" value="1" class="h-4 w-4 rounded border-gray-300 text-primary" {{ old('ativo', $faixa->ativo) ? 'checked' : '' }}>
                    <span class="text-sm font-semibold text-gray-700">Faixa ativa</span>
                </label>
            </div>

            <div class="mb-6">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Texto no e-mail *</span>
                    <textarea name="texto_email" rows="7" required class="mt-2 w-full rounded border border-gray-300 p-3">{{ old('texto_email', $faixa->texto_email) }}</textarea>
                </label>
                <p class="text-xs text-gray-500 mt-2">
                    Placeholders: {NOME}, {X}, {Y}, {Z}, {DIAS_ESTUDADOS}, {DIAS_ANALISADOS}, {DIAS_FALHADOS},
                    {TOTAL_QUESTOES}, {PERCENTUAL_ACERTOS}, {ASSUNTO}, {PERCENTUAL}, {DISCIPLINA}
                </p>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('desempenho.index') }}" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded font-medium">Cancelar</a>
                <button type="submit" class="px-6 py-3 bg-primary hover:bg-primary-light text-white rounded font-medium">Salvar</button>
            </div>
        </form>
    </div>
@endsection
