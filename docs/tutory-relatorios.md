# Relatórios do Coach (Tutory)

Automação PHP (Selenium + Firefox) que baixa o **Relatório do Coach** dos alunos **ativos** no admin Tutory.

## Requisitos

- PHP 8.2+
- Firefox (binário real, não wrapper snap em `/usr/bin/firefox`)
- geckodriver no `PATH` (o `php-webdriver` sobe o serviço automaticamente)
- Credenciais no `.env`

## Variáveis no `.env`

```env
LOGIN_URL=https://admin.tutory.com.br/login
LOGIN_USER=
LOGIN_PASSWORD=
PASTA_DOWNLOAD=/caminho/para/Relatorios_Tutory
FIREFOX_BINARY=/usr/lib/firefox/firefox
# FIREFOX_PROFILE=
HEADLESS=0
TIMEOUT=25
DOWNLOAD_TIMEOUT=90
CHART_WAIT=30
```

## Uso

Via Artisan:

```bash
# Período 1–15 do mês atual
php artisan tutory:baixar-relatorios --periodo=1

# Período 16–último dia
php artisan tutory:baixar-relatorios --periodo=2

# Teste com uma aluna (valida PDF com gráficos)
php artisan tutory:baixar-relatorios --periodo=1 --teste
```

Via script CLI (mesmo fluxo do Python original):

```bash
php scripts/baixar_relatorios_tutory.php --periodo=1
php scripts/baixar_relatorios_tutory.php --periodo=2 --teste
```

## Fluxo

1. Login
2. `/alunos/consulta` com filtro `status=ativos` + Buscar
3. Para cada aluno: Opções → Relatório do Coach → filtros → Gerar → Acessar → Baixar
4. Congela o gráfico Chart.js (`#chart_questoes_dia`) em PNG antes do PDF
5. Reprocessa falhas (até 3 tentativas por aluno)
6. Grava log `log_download_YYYYMMDD_HHMMSS.txt` na pasta de download
