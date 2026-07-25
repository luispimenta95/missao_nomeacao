@php
    $regrasDefault = $nivel->regras->map(fn ($r) => [
        'criterio_desempenho_id' => $r->criterio_desempenho_id,
        'operador' => $r->operador,
        'valor_min' => $r->valor_min,
        'valor_max' => $r->valor_max,
    ])->values()->all();
    if ($regrasDefault === []) {
        $regrasDefault = [['criterio_desempenho_id' => '', 'operador' => '>=', 'valor_min' => '', 'valor_max' => '']];
    }
    $regrasOld = old('regras', $regrasDefault);
@endphp

@extends('layouts.admin')

@section('title', 'Editar nível de desempenho')

@section('content')
    <div>
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Editar nível: {{ $nivel->nome }}</h1>
            <a href="{{ route('desempenho.index') }}" class="text-sm text-primary hover:text-primary-light">← Voltar</a>
        </div>

        @if($errors->any())
            <div class="p-4 mb-4 bg-red-100 text-red-800 rounded border border-red-300">
                <p class="font-bold">Erro ao editar nível:</p>
                <ul class="list-disc list-inside mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('desempenho.update', $nivel) }}" method="POST" class="bg-white p-8 rounded shadow-lg max-w-4xl" id="form-desempenho">
            @csrf
            @method('PUT')
            @include('admin.desempenho._form', [
                'regras' => $regrasOld,
                'nivel' => $nivel,
            ])
        </form>

        <div class="mt-8 p-6 bg-red-50 border border-red-200 rounded-lg max-w-4xl">
            <h3 class="text-lg font-bold text-red-800 mb-2">Zona de Perigo</h3>
            <p class="text-sm text-red-700 mb-4">Remover o nível também remove suas regras.</p>
            <form action="{{ route('desempenho.destroy', $nivel) }}" method="POST" onsubmit="return confirm('Tem certeza que deseja remover este nível?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded font-medium transition">Remover nível</button>
            </form>
        </div>
    </div>
@endsection
