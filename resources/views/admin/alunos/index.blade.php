@extends('layouts.admin')

@section('title', 'Gerenciar Alunos')

@section('content')
<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Gerenciar Alunos</h1>
        <a href="{{ route('alunos.create') }}" class="px-4 py-2 bg-primary hover:bg-primary-light text-white rounded transition">+ Novo Aluno</a>
    </div>

    @if(session('success'))
    <div class="p-4 bg-green-100 text-green-800 rounded mb-4">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded shadow overflow-hidden">
        <div class="p-4 border-b border-gray-100">
            <label class="block max-w-md">
                <span class="text-sm font-semibold text-gray-700">Buscar por nome</span>
                <input type="search" id="busca-aluno" name="busca" value="{{ $busca }}" placeholder="Digite o nome do aluno" autocomplete="off" class="mt-2 w-full rounded border border-gray-300 p-3 focus:ring-primary focus:border-primary">
            </label>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nome</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">E-mail</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Constância</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Questões</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">% acertos</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Assuntos</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Recebe e-mail</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody id="lista-alunos" class="divide-y divide-gray-100">
                    @include('admin.alunos._linhas')
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    (function() {
        const campo = document.getElementById('busca-aluno');
        const lista = document.getElementById('lista-alunos');
        if (!campo || !lista) {
            return;
        }

        const urlBase = @json(route('alunos.index'));
        let timer = null;
        let pedido = 0;
        let abortar = null;

        function urlComBusca(valor) {
            const url = new URL(urlBase, window.location.origin);
            const termo = valor.trim();
            if (termo !== '') {
                url.searchParams.set('busca', termo);
            }
            return url;
        }

        async function filtrar(valor) {
            const n = ++pedido;
            if (abortar) {
                abortar.abort();
            }
            abortar = new AbortController();
            const url = urlComBusca(valor);
            lista.classList.add('opacity-60');
            try {
                const res = await fetch(url.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html'
                    },
                    signal: abortar.signal
                });
                if (!res.ok || n !== pedido) {
                    return;
                }
                lista.innerHTML = await res.text();
                const visivel = urlComBusca(valor);
                history.replaceState(null, '', visivel.pathname + visivel.search);
            } catch (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
            } finally {
                if (n === pedido) {
                    lista.classList.remove('opacity-60');
                }
            }
        }

        campo.addEventListener('input', function() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                filtrar(campo.value);
            }, 200);
        });
    })();
</script>
@endsection
