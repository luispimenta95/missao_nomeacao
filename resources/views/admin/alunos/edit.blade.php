@extends('layouts.admin')

@section('title', 'Editar Aluno')

@section('content')
    <div>
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Editar Aluno</h1>
            <a href="{{ route('alunos.index') }}" class="text-sm text-primary hover:text-primary-light">← Voltar para alunos</a>
        </div>

        @if($errors->any())
            <div class="p-4 mb-4 bg-red-100 text-red-800 rounded border border-red-300">
                <p class="font-bold">Erro ao editar aluno:</p>
                <ul class="list-disc list-inside mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('alunos.update', $aluno) }}" method="POST" class="bg-white p-8 rounded shadow-lg max-w-2xl">
            @csrf
            @method('PUT')

            <div class="mb-6">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Nome *</span>
                    <input type="text" name="nome" required class="mt-2 w-full rounded border border-gray-300 p-3 focus:ring-primary focus:border-primary @error('nome') border-red-500 @enderror" value="{{ old('nome', $aluno->nome) }}">
                    @error('nome') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </label>
            </div>

            <div class="mb-6">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">E-mail *</span>
                    <input type="email" name="email" required class="mt-2 w-full rounded border border-gray-300 p-3 focus:ring-primary focus:border-primary @error('email') border-red-500 @enderror" value="{{ old('email', $aluno->email) }}">
                    @error('email') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </label>
            </div>

            <div class="mb-8">
                <label class="inline-flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="recebe_email" value="1" class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary" {{ old('recebe_email', $aluno->recebe_email) ? 'checked' : '' }}>
                    <span class="text-sm font-semibold text-gray-700">Recebe e-mail</span>
                </label>
                <p class="text-xs text-gray-500 mt-1">Se marcado, o aluno poderá receber e-mails do sistema.</p>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('alunos.index') }}" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded font-medium transition">Cancelar</a>
                <button type="submit" class="px-6 py-3 bg-primary hover:bg-primary-light text-white rounded font-medium transition">Atualizar Aluno</button>
            </div>
        </form>

        <div class="mt-8 p-6 bg-red-50 border border-red-200 rounded-lg max-w-2xl">
            <h3 class="text-lg font-bold text-red-800 mb-2">Zona de Perigo</h3>
            <p class="text-sm text-red-700 mb-4">Uma vez deletado, o aluno não pode ser recuperado.</p>
            <form action="{{ route('alunos.destroy', $aluno) }}" method="POST" onsubmit="return confirm('Tem certeza que deseja deletar este aluno?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded font-medium transition">Deletar Aluno</button>
            </form>
        </div>
    </div>
@endsection
