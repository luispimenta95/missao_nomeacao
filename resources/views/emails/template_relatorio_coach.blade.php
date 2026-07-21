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
        <p>Seu <strong>Relatório do Coach</strong> está pronto.</p>
        @if(!empty($dados['periodoLabel']))
            <p>Período: <strong>{{ $dados['periodoLabel'] }}</strong>.</p>
        @endif
        <p>O PDF segue em anexo neste e-mail.</p>
        <p>Qualquer dúvida, estamos à disposição.</p>
    </div>
    <div class="footer">
        <p>Atenciosamente, <br>
            {{ config('app.name') }}</p>
        <br>
        <p>&copy; {{ date('Y') }} Todos os direitos reservados.</p>
    </div>
</div>
</body>
</html>
