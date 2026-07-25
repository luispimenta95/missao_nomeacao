@extends('layouts.admin')

@section('title', 'Gestão de Desempenho')

@section('content')
    <div>
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Gestão de Desempenho</h1>
            <p class="text-sm text-gray-600 mt-1 max-w-3xl">
                Parâmetros do relatório do coach: constância, volume de questões, percentual geral e desempenho por assunto.
                Os textos usam placeholders como <code class="bg-gray-100 px-1 rounded">{NOME}</code>,
                <code class="bg-gray-100 px-1 rounded">{X}</code>, <code class="bg-gray-100 px-1 rounded">{Y}</code>,
                <code class="bg-gray-100 px-1 rounded">{Z}</code>, <code class="bg-gray-100 px-1 rounded">{TOTAL_QUESTOES}</code>,
                <code class="bg-gray-100 px-1 rounded">{PERCENTUAL_ACERTOS}</code>,
                <code class="bg-gray-100 px-1 rounded">{ASSUNTO}</code> e <code class="bg-gray-100 px-1 rounded">{PERCENTUAL}</code>.
            </p>
        </div>

        @if(session('success'))
            <div class="p-4 bg-green-100 text-green-800 rounded mb-4">{{ session('success') }}</div>
        @endif

        @forelse($eixos as $eixo)
            <div class="bg-white rounded shadow mb-6 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
                    <h2 class="text-lg font-bold text-gray-800">{{ $eixo->nome }}</h2>
                    @if($eixo->descricao)
                        <p class="text-sm text-gray-600 mt-1">{{ $eixo->descricao }}</p>
                    @endif
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-white">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Faixa</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Intervalo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($eixo->faixas as $faixa)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ $faixa->nome }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    @if($faixa->valor_min !== null && $faixa->valor_max !== null && (float)$faixa->valor_min === (float)$faixa->valor_max)
                                        = {{ rtrim(rtrim(number_format($faixa->valor_min, 2, ',', ''), '0'), ',') }}
                                    @elseif($faixa->valor_min !== null && $faixa->valor_max !== null)
                                        {{ rtrim(rtrim(number_format($faixa->valor_min, 2, ',', ''), '0'), ',') }}
                                        a
                                        {{ rtrim(rtrim(number_format($faixa->valor_max, 2, ',', ''), '0'), ',') }}
                                    @elseif($faixa->valor_min !== null)
                                        ≥ {{ rtrim(rtrim(number_format($faixa->valor_min, 2, ',', ''), '0'), ',') }}
                                    @elseif($faixa->valor_max !== null)
                                        ≤ {{ rtrim(rtrim(number_format($faixa->valor_max, 2, ',', ''), '0'), ',') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if($faixa->ativo)
                                        <span class="px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">Ativa</span>
                                    @else
                                        <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-600">Inativa</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('desempenho.edit', $faixa) }}" class="px-3 py-2 bg-primary hover:bg-primary-light text-white rounded text-sm transition">Editar texto</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <div class="bg-white rounded shadow p-8 text-center text-gray-600">
                Nenhum parâmetro cadastrado. Rode:
                <code class="block mt-2 bg-gray-100 p-2 rounded">php artisan db:seed --class=ParametrosDesempenhoSeeder</code>
            </div>
        @endforelse
    </div>
@endsection
