@php
    $regrasOld = old('regras', [['criterio_desempenho_id' => '', 'operador' => '>=', 'valor_min' => '', 'valor_max' => '']]);
@endphp

@extends('layouts.admin')

@section('title', 'Novo nível de desempenho')

@section('content')
    <div>
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Novo nível de desempenho</h1>
            <a href="{{ route('desempenho.index') }}" class="text-sm text-primary hover:text-primary-light">← Voltar</a>
        </div>

        @if($errors->any())
            <div class="p-4 mb-4 bg-red-100 text-red-800 rounded border border-red-300">
                <p class="font-bold">Erro ao criar nível:</p>
                <ul class="list-disc list-inside mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('desempenho.store') }}" method="POST" class="bg-white p-8 rounded shadow-lg max-w-4xl" id="form-desempenho">
            @csrf
            @include('admin.desempenho._form', ['regras' => $regrasOld])
        </form>
    </div>
@endsection
