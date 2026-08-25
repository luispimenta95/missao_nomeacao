<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .content {
            font-size: 16px;
            color: #555;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .bloco {
            margin: 16px 0;
            padding: 14px 16px;
            background: #f8f4e8;
            border-left: 4px solid #BF8F00;
            border-radius: 4px;
        }

        .bloco h3 {
            margin: 0 0 8px;
            font-size: 15px;
            color: #222;
        }

        .bloco p {
            margin: 0;
            color: #444;
        }

        .footer {
            font-size: 14px;
            color: #888;
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="content">
            <p>Olá, {{ $dados['nome'] }}!</p>
            <strong>
            <p>Seu relatório consolidado da mentoria está pronto.</p>
            </strong>
            @if(!empty($dados['periodoLabel']))
            <p>Período: <strong>{{ $dados['periodoLabel'] }}</strong>.</p>
            @endif

            @if(!empty($dados['blocosDesempenho']) && is_array($dados['blocosDesempenho']))
            <p><strong>Análise de desempenho do período</strong></p>
            @foreach($dados['blocosDesempenho'] as $bloco)
            <div class="bloco">
                @if(!empty($bloco['titulo']))
                <h3>{{ $bloco['titulo'] }}</h3>
                @endif
                @if(!empty($bloco['itens']) && is_array($bloco['itens']))
                <ul style="margin:0 0 12px;padding-left:20px;">
                    @foreach($bloco['itens'] as $item)
                    <li style="margin:0 0 6px;">{{ $item }}</li>
                    @endforeach
                </ul>
                @php
                $textoBloco = (string) ($bloco['texto'] ?? '');
                // Remove a lista textual se o e-mail já renderizou bullets em HTML
                $textoBloco = trim(preg_replace('/^•.+(?:\n•.+)*\n*/mu', '', $textoBloco) ?? $textoBloco);
                @endphp
                @if($textoBloco !== '')
                <p style="white-space:pre-line;margin:0;">{{ $textoBloco }}</p>
                @endif
                @elseif(!empty($bloco['texto']))
                <p style="white-space:pre-line;margin:0;">{{ $bloco['texto'] }}</p>
                @endif
                @if(!empty($bloco['cta']['url']))
                <p style="margin:14px 0 0;">
                    <a href="{{ $bloco['cta']['url'] }}" target="_blank" rel="noopener"
                       style="display:inline-block;background:#BF8F00;color:#ffffff;text-decoration:none;padding:10px 16px;font-weight:bold;font-size:14px;">
                        {{ $bloco['cta']['label'] ?? 'Quero adiantar minha análise' }}
                    </a>
                </p>
                @endif
            </div>
            @endforeach
            @elseif(!empty($dados['textoDesempenho']))
            <div class="bloco">
                @if(!empty($dados['nivelDesempenho']))
                <h3>Nível de desempenho: {{ $dados['nivelDesempenho'] }}</h3>
                @endif
                <p>{{ $dados['textoDesempenho'] }}</p>
            </div>
            @endif

            @if(!empty($dados['relatorios']) && is_array($dados['relatorios']))
            <p>Anexos:</p>
            <ul>
                @foreach($dados['relatorios'] as $relatorio)
                <li>{{ $relatorio }}</li>
                @endforeach
            </ul>
            @endif
            <p>O PDF segue em anexo neste e-mail.</p>
            <p>Qualquer dúvida, estamos à disposição.</p>
        </div>
        <div class="footer">
            <p>Atenciosamente, <br>
                Equipe Missão Nomeação</p>
            <br>
            <p>&copy; {{ date('Y') }} Todos os direitos reservados.</p>
        </div>
    </div>
</body>

</html>