<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a;">
    <p>Segue o resumo da execução dos relatórios do Coach.</p>
    <p>
        Data: {{ $dados['data'] ?? '—' }}<br>
        Período: {{ $dados['periodo'] ?? '—' }}<br>
        Início: {{ $dados['inicio'] ?? '—' }}
        &nbsp; Fim: {{ $dados['fim'] ?? '—' }}
        &nbsp; Duração: {{ $dados['duracao'] ?? '—' }}
    </p>
    <p>O PDF em anexo lista os alunos, o que foi gerado e o que aconteceu no envio.</p>
</body>
</html>
