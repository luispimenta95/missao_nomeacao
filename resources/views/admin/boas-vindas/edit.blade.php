@extends('layouts.admin')

@section('title', 'Boas-vindas do aluno novato')

@section('content')
    <div>
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Boas-vindas do aluno novato</h1>
            <p class="text-sm text-gray-600 mt-1 max-w-3xl">
                Este é o único texto do e-mail enviado no lugar do PDF quando o cadastro tem menos de 15 dias.
                Não há variações nem rodízio. Use <code class="bg-gray-100 px-1 rounded">{NOME}</code> para o nome do aluno.
            </p>
        </div>

        @if(session('success'))
            <div class="p-4 bg-green-100 text-green-800 rounded mb-4">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="p-4 mb-4 bg-red-100 text-red-800 rounded border border-red-300">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('boas-vindas.update') }}" method="POST" class="bg-white p-8 rounded shadow-lg max-w-3xl">
            @csrf
            @method('PUT')

            <label class="block">
                <span class="text-sm font-semibold text-gray-700">Texto do e-mail *</span>
                <textarea name="texto" required rows="14" class="mt-2 w-full rounded border border-gray-300 p-3">{{ old('texto', $texto) }}</textarea>
            </label>

            <div class="mt-6">
                <button type="submit" class="px-4 py-3 bg-primary hover:bg-primary-light text-white rounded transition">Salvar texto</button>
            </div>
        </form>
    </div>
@endsection
