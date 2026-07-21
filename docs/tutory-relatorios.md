# Relatórios do Coach (Tutory)

CLI PHP **somente HTTP** (sem Selenium, sem Firefox, sem php-webdriver) que baixa o **Relatório do Coach** dos alunos **ativos** no admin Tutory.

## Requisitos

- PHP 8.2+
- Credenciais no `.env`
- Acesso de rede a `admin.tutory.com.br`

## Variáveis no `.env`

```env
LOGIN_URL=https://admin.tutory.com.br/login
LOGIN_USER=
LOGIN_PASSWORD=
PASTA_DOWNLOAD=
TIMEOUT=60

# Opcional: se a descoberta automática falhar, force as rotas
# TUTORY_REPORT_GENERATE_URL=/intent/gerar-relatorio-coach
# TUTORY_REPORT_DOWNLOAD_URL=
```

## Uso

```bash
# Período 1–15 do mês atual
php scripts/baixar_relatorios_tutory.php --periodo=1

# Período 16–último dia
php scripts/baixar_relatorios_tutory.php --periodo=2

# Teste com uma aluna
php scripts/baixar_relatorios_tutory.php --periodo=1 --teste

# Alternativa Artisan (mesmo serviço)
php artisan tutory:baixar-relatorios --periodo=1
```

## Fluxo

1. `POST /intent/login` (cookie de sessão + Bearer token do HTML, se existir)
2. `/alunos/consulta` com `status=ativos` (GET/POST/form)
3. Para cada aluno: descobre endpoint de geração → solicita relatório (questões + mês + datas) → baixa PDF via HTTP
4. Reprocessa falhas (até 3 tentativas por aluno)
5. Grava `log_download_YYYYMMDD_HHMMSS.txt` na pasta de download

Se o painel só gerar PDF no navegador (botão Baixar/jsPDF), o CLI salva o HTML em `debug_*.html` para inspeção e permite forçar rotas com `TUTORY_REPORT_*`.
