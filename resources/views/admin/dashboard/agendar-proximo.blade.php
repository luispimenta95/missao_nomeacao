@extends('layouts.admin')

@section('title', 'Agendar próximo contato')

@section('content')
<div class="mx-auto max-w-xl">
    <div class="rounded-3xl border border-[#e6ebf2] bg-white p-6 shadow-sm sm:p-8">
        <div class="flex items-center gap-4">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-[#001d3d] text-base font-semibold text-white">{{ $iniciais }}</span>
            <div>
                <p class="text-sm font-medium text-[#15803d]">Contato registrado</p>
                <h1 class="text-2xl font-semibold text-[#12263f]">Deseja agendar um contato para {{ $aluno->nome }}?</h1>
            </div>
        </div>

        <p class="mt-4 text-sm leading-6 text-[#64748b]">
            Se preferir não escolher a data, o próximo contato fica em {{ $dataPadraoTexto }}, 15 dias corridos contando hoje.
        </p>

        @if($errors->any())
            <div class="mt-4 rounded-xl bg-[#fff1f2] px-4 py-3 text-sm text-[#be123c]">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.dashboard.contatos.agendar.store', $aluno) }}" class="mt-6 space-y-4">
            @csrf
            <label class="block text-sm font-medium text-[#12263f]">
                Nova data
                <input type="date" name="proximo_contato_em" min="{{ $minData }}" value="{{ old('proximo_contato_em') }}" class="mt-2 w-full rounded-xl border border-[#d7dee8] px-3 py-3 text-sm text-[#12263f] focus:border-[#001d3d] focus:outline-none">
            </label>
            <button type="submit" name="decisao" value="sim" class="flex w-full items-center justify-center rounded-xl bg-[#001d3d] px-4 py-3 text-sm font-semibold text-white hover:bg-[#03284f]">
                Sim, agendar nesta data
            </button>
            <button type="submit" name="decisao" value="nao" class="flex w-full items-center justify-center rounded-xl border border-[#d7dee8] px-4 py-3 text-sm font-semibold text-[#12263f] hover:bg-[#f8fafc]">
                Não, agendar para {{ $dataPadraoTexto }}
            </button>
        </form>
    </div>
</div>
@endsection
