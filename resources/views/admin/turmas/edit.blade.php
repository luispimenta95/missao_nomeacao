@extends('layouts.admin')

@section('title', 'Editar Turma')

@section('content')
    <div>
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Editar Turma</h1>
            <p class="text-sm text-gray-600 mt-1">Estágio atual no site: <strong>{{ $turma->badgePublico() }}</strong> · CTA: <strong>{{ $turma->textoCtaPublico() }}</strong></p>
            <a href="{{ route('turmas.index') }}" class="text-sm text-primary hover:text-primary-light">← Voltar para turmas</a>
        </div>

        @include('admin.turmas._form')

        <div class="mt-8 p-6 bg-red-50 border border-red-200 rounded-lg max-w-4xl">
            <h3 class="text-lg font-bold text-red-800 mb-2">Zona de Perigo</h3>
            <p class="text-sm text-red-700 mb-4">Uma vez deletada, a turma some também das listagens do site Missão Nomeação.</p>
            <form action="{{ route('turmas.destroy', $turma) }}" method="POST" onsubmit="return confirm('Tem certeza que deseja deletar esta turma?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded font-medium">Deletar Turma</button>
            </form>
        </div>
    </div>
@endsection
