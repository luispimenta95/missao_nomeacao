<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Conversar com {{ $nome }}</title>
    <script>
        location.replace(@json($url));
    </script>
</head>
<body>
    <p>Abrindo a conversa com {{ $nome }}.</p>
    <p><a href="{{ $url }}">Continuar para o WhatsApp</a></p>
</body>
</html>
