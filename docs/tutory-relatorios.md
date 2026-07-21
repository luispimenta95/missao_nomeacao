# Relatórios do Coach (Tutory)

CLI PHP **HTTP + Puppeteer** que baixa o Relatório do Coach dos alunos **ativos** no Tutory e envia o PDF por e-mail aos alunos cadastrados no admin (`recebe_email=true`).

## Fluxo

1. `POST /intent/login` → sessão + Bearer (`adminUser.token`)
2. `GET /alunos/consulta?status=ativos` → `data-id` de cada aluno
3. `POST /intent/cadastrar-relatorio-coach` (`alunos[]`, `dt_ini`, `dt_fim`, `agrupamento=dia`)
4. `GET /documentos/relatorios/questoes?key=...`
5. PDF oficial via **Puppeteer** (`scripts/tutory-render-pdf.mjs` → `PDFWriter.output()`)
6. Fallback: Dompdf + QuickChart (se Node/Chromium indisponível)
7. Reprocessa falhas (até 3 tentativas)
8. Lista alunos do **admin** (`alunos`) → localiza PDF em `public/pdfs` pelo nome → envia e-mail se `recebe_email=true` (PDF em anexo)

## Requisitos

- PHP 8.2+
- Node.js 18+ com `puppeteer` (`npm install`) — Chromium baixado pelo Puppeteer
- Rede para `admin.tutory.com.br`
- (Fallback Dompdf) extensão **gd** + acesso a `quickchart.io`
- SMTP configurado (`MAIL_*`) para envio dos relatórios
- Alunos cadastrados em `/admin/alunos` com o **mesmo nome** usado no Tutory (para casar o PDF)

## `.env`

```env
LOGIN_URL=https://admin.tutory.com.br/login
LOGIN_USER=
LOGIN_PASSWORD=
# Preferível fora de public/ (evita expor HTML/token)
PASTA_DOWNLOAD=
TIMEOUT=120
APP_TIMEZONE=America/Sao_Paulo
# NODE_BINARY=node
# TUTORY_REPORT_GENERATE_URL=/intent/cadastrar-relatorio-coach
# TUTORY_REPORT_MODEL=questoes
# TUTORY_REPORT_AGRUPAMENTO=dia
```

Em teste, o serviço pode usar credenciais hardcoded. Em produção, use `LOGIN_USER` / `LOGIN_PASSWORD`.

Se `PASTA_DOWNLOAD` estiver vazio, usa `public/pdfs`.

## Uso

```bash
npm install   # puppeteer + Chromium

php artisan tutory:baixar-relatorios --periodo=1
php artisan tutory:baixar-relatorios --periodo=2 --teste
```

Arquivo gerado: `relatorio_{Ymd_Hi}_{aluno}_{periodo}.pdf`  
Exemplo: `relatorio_20260721_1830_Laíra_Lacerda_1.pdf`

O envio de e-mail usa o PDF mais recente do aluno na pasta para o `--periodo` informado (aceita pequenas diferenças de digitação no nome).

## Agendamento (Laravel Scheduler)

Em `routes/console.php`:

| Job | Comando | Quando |
|-----|---------|--------|
| Periodo 1 | `tutory:baixar-relatorios --periodo=1` | Dia **16** de cada mês, **00:00** |
| Periodo 2 | `tutory:baixar-relatorios --periodo=2` | **Último dia** do mês, **00:00** |

No servidor (cron):

```cron
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

Conferir: `php artisan schedule:list`

## Segurança

- **Não** salve dumps de HTML do admin em `public/` (o `adminUser.token` vaza).
- Prefira `LOGIN_USER` / `LOGIN_PASSWORD` no `.env` (hardcode só para teste).
- Se um `debug_*.html` já ficou público, revogue a sessão no Tutory e apague o arquivo.
