<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de e-mail — Missão Nomeação</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            width: 100%;
            max-width: 640px;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 28px;
        }
        h1 {
            margin: 0 0 8px;
            font-size: 1.5rem;
        }
        .status {
            display: inline-block;
            margin: 16px 0;
            padding: 8px 14px;
            border-radius: 6px;
            font-weight: 700;
        }
        .ok {
            background: #14532d;
            color: #86efac;
        }
        .fail {
            background: #7f1d1d;
            color: #fecaca;
        }
        .meta {
            margin-top: 20px;
            font-size: 0.9rem;
            color: #94a3b8;
            line-height: 1.6;
        }
        .error-box {
            margin-top: 16px;
            padding: 14px;
            background: #450a0a;
            border: 1px solid #991b1b;
            border-radius: 8px;
            color: #fecaca;
            white-space: pre-wrap;
            word-break: break-word;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.85rem;
        }
        a {
            color: #38bdf8;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Teste de envio de e-mail</h1>
        <p>Destinatário: <strong>{{ $to }}</strong></p>

        <div class="status {{ $success ? 'ok' : 'fail' }}">
            {{ $success ? 'SUCESSO' : 'ERRO' }}
        </div>

        <p>{{ $message }}</p>

        @if($error)
            <div class="error-box">{{ $error }}</div>
        @endif

        <div class="meta">
            <div>Mailer: {{ $mailer }}</div>
            <div>From: {{ $from }}</div>
            <div>SMTP host: {{ $host }}</div>
            <div style="margin-top: 12px;">
                <a href="{{ route('mail.test') }}">Tentar novamente</a>
            </div>
        </div>
    </div>
</body>
</html>
