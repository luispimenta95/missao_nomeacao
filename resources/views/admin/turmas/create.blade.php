@extends('layouts.admin')

@section('title', 'Nova Turma')

@section('content')
    <div>
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Nova Turma</h1>
            <p class="text-sm text-gray-600 mt-1">Essa preparação será publicada no site Missão Nomeação conforme os flags de visibilidade.</p>
            <a href="{{ route('turmas.index') }}" class="text-sm text-primary hover:text-primary-light">← Voltar para turmas</a>
        </div>

        @include('admin.turmas._form')
    </div>
@endsection
