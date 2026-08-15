@extends('layouts.admin')

@section('title', 'PDF do relatório')

@section('content')
    <div>
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-white">PDF do relatório</h1>
            <p class="text-gray-300 mt-2">Escolha a fonte usada na geração do PDF (Dompdf) e veja um preview real.</p>
        </div>

        @if(session('success'))
            <div class="p-4 mb-6 bg-green-100 text-green-800 rounded border border-green-300">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 mb-6 bg-red-100 text-red-800 rounded border border-red-300">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
            <form action="{{ route('relatorios-pdf.update') }}" method="POST" class="bg-white p-8 rounded shadow-lg">
                @csrf
                @method('PUT')

                <h2 class="text-lg font-bold text-gray-800 mb-1">Fonte</h2>
                <p class="text-sm text-gray-500 mb-6">Só famílias padrão, para o PDF abrir igual no Adobe, Chrome, Preview, iOS e Android.</p>

                <div class="space-y-3 mb-8">
                    @foreach($opcoes as $chave => $opcao)
                        <label class="flex gap-4 p-4 rounded border cursor-pointer hover:border-yellow-600 transition {{ $fonteAtual === $chave ? 'border-yellow-600 bg-yellow-50' : 'border-gray-200' }}">
                            <input type="radio" name="fonte" value="{{ $chave }}" class="mt-1 h-4 w-4 text-yellow-600 focus:ring-yellow-600"
                                {{ old('fonte', $fonteAtual) === $chave ? 'checked' : '' }}
                                data-web-font="{{ $opcao['web'] }}">
                            <span>
                                <span class="block font-semibold text-gray-800" style="font-family: {{ $opcao['web'] }}">{{ $opcao['nome'] }}</span>
                                <span class="block text-sm text-gray-600 mt-1">{{ $opcao['descricao'] }}</span>
                                @if(! $opcao['acentos'])
                                    <span class="inline-block mt-2 text-xs font-medium text-amber-800 bg-amber-100 px-2 py-0.5 rounded">Sem ã / ç</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="flex flex-wrap gap-3 justify-end">
                    <a id="pdf-open" href="{{ route('relatorios-pdf.preview', ['fonte' => $fonteAtual]) }}" target="_blank" rel="noopener"
                       class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded font-medium transition">
                        Abrir PDF em nova aba
                    </a>
                    <button type="submit" class="px-6 py-3 bg-primary hover:bg-primary-light text-white rounded font-medium transition">
                        Salvar fonte
                    </button>
                </div>
            </form>

            <div>
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-bold text-white">Preview</h2>
                    <span class="text-xs text-gray-400">PDF gerado de verdade (não é uma imagem)</span>
                </div>
                <div class="bg-gray-900 rounded-lg border border-yellow-600 overflow-hidden" style="min-height: 720px;">
                    <iframe
                        id="pdf-preview"
                        title="Preview do PDF"
                        src="{{ route('relatorios-pdf.preview', ['fonte' => $fonteAtual]) }}"
                        class="w-full bg-white"
                        style="height: 820px;"
                    ></iframe>
                </div>
                <p class="text-xs text-gray-400 mt-3">Troque a fonte à esquerda para atualizar o preview sem salvar. Clique em Salvar para valer na geração dos relatórios.</p>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const iframe = document.getElementById('pdf-preview');
            const openLink = document.getElementById('pdf-open');
            const base = @json(route('relatorios-pdf.preview'));
            document.querySelectorAll('input[name="fonte"]').forEach(function (input) {
                input.addEventListener('change', function () {
                    const url = base + '?fonte=' + encodeURIComponent(input.value);
                    iframe.src = url;
                    openLink.href = url;
                    document.querySelectorAll('input[name="fonte"]').forEach(function (el) {
                        el.closest('label').classList.toggle('border-yellow-600', el.checked);
                        el.closest('label').classList.toggle('bg-yellow-50', el.checked);
                        el.closest('label').classList.toggle('border-gray-200', !el.checked);
                    });
                });
            });
        })();
    </script>
@endsection
