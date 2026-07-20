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
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #333;
        }
        .content {
            font-size: 16px;
            color: #555;
            line-height: 1.5;
            margin-bottom: 20px;
        }
        .btn-container {
            text-align: center;
        }
        .btn {
            display: inline-block;
            background-color: #1e90ff;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            font-size: 16px;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }
        .btn:hover {
            background-color: #1873cc;
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
        <p>Recebemos sua inscrição
            @if(!empty($dados['tituloTurma']))
                na turma <strong>{{ $dados['tituloTurma'] }}</strong>
            @endif
            da Mentoria Missão Nomeação.
        </p>
        @if(!empty($dados['url']))
            <p>Para concluir sua inscrição, finalize o pagamento no botão abaixo:</p>
        @else
            <p>Em breve entraremos em contato com os próximos passos.</p>
        @endif
    </div>
    @if(!empty($dados['url']))
        <div class="btn-container">
            <a href="{{ $dados['url'] }}" class="btn">Ir para o checkout</a>
        </div>
    @endif
    <div class="footer">
        <p>Se você não se inscreveu, ignore este e-mail.</p>
        <p>Atenciosamente, <br>
            {{ config('app.name') }}</p>
        <br>
        <p>&copy; {{ date('Y') }} Todos os direitos reservados.</p>
    </div>
</div>
</body>
</html>
