@extends('layouts.admin')

@section('title', 'PDF de meses anteriores')

@section('content')
    <div>
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-white">PDF de meses anteriores</h1>
            <p class="text-gray-300 mt-2">Contingência: gera o mesmo PDF de desempenho de um aluno e disponibiliza para download (sem envio por e-mail).</p>
        </div>

        @if($errors->any())
            <div class="p-4 mb-6 bg-red-100 text-red-800 rounded border border-red-300">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('relatorios-pdf-contingencia.gerar') }}" method="POST" class="bg-white p-8 rounded shadow-lg max-w-2xl">
            @csrf

            <div class="mb-6">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Aluno *</span>
                    <select name="aluno_id" required class="mt-2 w-full rounded border border-gray-300 p-3 bg-white focus:ring-primary focus:border-primary @error('aluno_id') border-red-500 @enderror">
                        <option value="">Selecione um aluno ativo da Tutory</option>
                        @foreach($alunos as $aluno)
                            <option value="{{ $aluno->id }}" {{ (string) old('aluno_id') === (string) $aluno->id ? 'selected' : '' }}>
                                {{ $aluno->nome }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <p class="text-xs text-gray-500 mt-2">Lista dos alunos sincronizados como ativos na Tutory. Um aluno por geração.</p>
                @if($alunos->isEmpty())
                    <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded p-3 mt-3">Nenhum aluno com id da Tutory. Rode a sincronização de alunos ativos e volte aqui.</p>
                @endif
            </div>

            <div class="mb-8">
                <label class="block">
                    <span class="text-sm font-semibold text-gray-700">Mês / período *</span>
                    <select name="periodo" required class="mt-2 w-full rounded border border-gray-300 p-3 bg-white focus:ring-primary focus:border-primary @error('periodo') border-red-500 @enderror">
                        <option value="">Selecione o período</option>
                        @foreach($periodos as $periodo)
                            <option value="{{ $periodo['chave'] }}" {{ (string) old('periodo') === (string) $periodo['chave'] ? 'selected' : '' }}>
                                {{ $periodo['label'] }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <p class="text-xs text-gray-500 mt-2">O período 1 libera no dia 16 do próprio mês. O período 2 libera no dia 1 do mês seguinte. O mais antigo é janeiro/2026 período 1. Períodos futuros não aparecem.</p>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-3 bg-primary hover:bg-primary-light text-white rounded font-medium transition" {{ $alunos->isEmpty() || $periodos === [] ? 'disabled' : '' }}>
                    Gerar PDF
                </button>
            </div>
        </form>
    </div>
@endsection
