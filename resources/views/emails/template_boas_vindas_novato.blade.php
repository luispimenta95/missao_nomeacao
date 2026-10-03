<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a; line-height: 1.5;">
    @foreach(preg_split("/\r\n|\n|\r/", (string) ($dados['texto'] ?? '')) as $linha)
        @if(trim($linha) === '')
            <p style="margin: 0 0 12px;">&nbsp;</p>
        @else
            <p style="margin: 0 0 12px;">{{ $linha }}</p>
        @endif
    @endforeach
</body>
</html>
