@extends('layouts.admin')

@section('title', 'Agendar próximo contato')

@section('content')
<div class="mx-auto max-w-xl">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Agendar próximo contato</h1>
        <a href="{{ route('admin.dashboard') }}" class="text-sm text-primary hover:text-primary-light">← Voltar para o dashboard</a>
    </div>

    <div class="rounded bg-white p-8 shadow-lg">
        <div class="flex items-center gap-4">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-primary text-base font-semibold text-white">{{ $iniciais }}</span>
            <div>
                <p class="text-sm font-medium text-green-800">Contato registrado</p>
                <p class="text-lg font-semibold text-gray-800">Deseja agendar um contato para {{ $aluno->nome }}?</p>
            </div>
        </div>

        <p class="mt-4 text-sm leading-6 text-gray-600">
            Se preferir não escolher a data, o próximo contato fica em {{ $dataPadraoTexto }}, 15 dias corridos contando hoje.
        </p>

        @if($errors->any())
            <div class="mt-4 rounded border border-red-300 bg-red-100 p-4 text-sm text-red-800">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.dashboard.contatos.agendar.store', $aluno) }}" class="mt-6 space-y-4">
            @csrf
            <label class="block">
                <span class="text-sm font-semibold text-gray-700">Nova data</span>
                <input type="date" name="proximo_contato_em" min="{{ $minData }}" value="{{ old('proximo_contato_em') }}" class="mt-2 w-full rounded border border-gray-300 p-3 text-sm text-gray-800 focus:border-primary focus:ring-primary">
            </label>
            <button type="submit" name="decisao" value="sim" class="flex w-full items-center justify-center rounded bg-primary px-6 py-3 text-sm font-medium text-white transition hover:bg-primary-light">
                Sim, agendar nesta data
            </button>
            <button type="submit" name="decisao" value="nao" class="flex w-full items-center justify-center rounded bg-gray-200 px-6 py-3 text-sm font-medium text-gray-800 transition hover:bg-gray-300">
                Não, agendar para {{ $dataPadraoTexto }}
            </button>
        </form>
    </div>
</div>
@endsection
