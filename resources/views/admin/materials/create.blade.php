@extends('components.layout')

@section('content')
    <div class="container mx-auto p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Cadastrar Material</h1>
            <a href="{{ route('materiais.index') }}" class="text-sm text-gray-600">← Voltar</a>
        </div>

        @if($errors->any())
            <div class="p-3 mb-4 bg-red-100 text-red-800 rounded">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('materiais.store') }}" method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded shadow">
            @csrf

            <label class="block mb-3">
                <span class="text-sm text-gray-600">Título</span>
                <input type="text" name="title" required class="mt-1 w-full rounded border-gray-200 p-2">
            </label>

            <label class="block mb-3">
                <span class="text-sm text-gray-600">Descrição</span>
                <textarea name="description" rows="3" class="mt-1 w-full rounded border-gray-200 p-2"></textarea>
            </label>

            <label class="block mb-3">
                <span class="text-sm text-gray-600">Arquivo (PDF)</span>
                <input type="file" name="file" accept="application/pdf" required class="mt-1">
            </label>

            <div class="mt-4 flex justify-end gap-3">
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded">Salvar Upload</button>
                <a href="{{ route('materiais.index') }}" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded border">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
