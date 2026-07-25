# Relatórios do Coach (Tutory)

CLI PHP **HTTP + Puppeteer** que baixa os relatórios do Coach dos alunos **ativos** no Tutory e envia os PDFs por e-mail aos alunos cadastrados no admin (`recebe_email=true`).

## Fluxo

1. `POST /intent/login` → sessão + Bearer (`adminUser.token`)
2. `GET /alunos/consulta?status=ativos` → `data-id` de cada aluno
3. Para **cada modelo** no array `RELATORIOS` (mesmo fluxo):
   1. `POST /intent/cadastrar-relatorio-coach` (`alunos[]`, `dt_ini`, `dt_fim`, `agrupamento=dia`)
   2. `GET /documentos/relatorios/{model}?key=...`
   3. PDF oficial via **Puppeteer** (`scripts/tutory-render-pdf.mjs` → `PDFWriter.output()` com download nativo CDP, igual ao botão Baixar)
   4. Fallback **Dompdf** + QuickChart se Node/Puppeteer falhar (`questoes` e `progresso`). No progresso, configs Chart.js com `backgroundColor: chartColors` são expandidas para cores fixas antes do QuickChart (Panorama, pizza e barras das páginas 2/4/5).
4. Reprocessa falhas por aluno+modelo (até 3 tentativas)
5. Lista alunos do **admin** (`alunos`) → localiza PDFs em `public/pdfs` pelo nome → envia **um único e-mail** com todos os PDFs em anexo se `recebe_email=true`

## Modelos (`RELATORIOS`)

Definidos em `CoachReportDownloader::RELATORIOS`:

| model (URL) | Nome |
|-------------|------|
| `questoes` | Desempenho em Questões |
| `progresso` | Progresso do plano |

Para incluir outro relatório do painel, adicione um índice ao array com o `model` usado em `/documentos/relatorios/{model}`.

## Requisitos

- PHP 8.2+
- Node.js 18+ com `puppeteer` (`npm install`) — Chromium baixado pelo Puppeteer
- Rede para `admin.tutory.com.br`
- (Fallback Dompdf, só questões) extensão **gd** + acesso a `quickchart.io`
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

Arquivo gerado: `relatorio_{model}_{Ymd_Hi}_{aluno}_{periodo}.pdf`  
Exemplos:
- `relatorio_questoes_20260721_1830_Laíra_Lacerda_1.pdf`
- `relatorio_progresso_20260721_1830_Laíra_Lacerda_1.pdf`

O envio de e-mail anexa o PDF mais recente de **cada modelo** do aluno para o `--periodo` informado (aceita pequenas diferenças de digitação no nome).

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

## Gestão de desempenho (admin)

Em `/admin/desempenho` o mentor cadastra **níveis** (Excelente, Bom, etc.) com **1 ou N critérios** baseados no panorama do Progresso do plano:

| Critério | Unidade | Origem no relatório |
|----------|---------|---------------------|
| Horas brutas | h | `.row-numbers` |
| Horas líquidas | h | `.row-numbers` |
| Dias | d | `.row-numbers` |
| Semanas | sem | `.row-numbers` |
| % questões | % | `.row-numbers` |

- Dentro de um nível, todos os critérios precisam ser atendidos (E lógico).
- A **ordem** menor é avaliada primeiro.
- Ao baixar o Progresso, as métricas são salvas em `*.metricas.json` ao lado do PDF.
- No e-mail do aluno entram o **nome do nível** e o **texto** cadastrado.

Seed dos critérios e níveis (Excelente → Requer atenção):

```bash
php artisan db:seed --class=CriteriosDesempenhoSeeder
php artisan db:seed --class=NiveisDesempenhoSeeder
```

## Segurança

- **Não** salve dumps de HTML do admin em `public/` (o `adminUser.token` vaza).
- Prefira `LOGIN_USER` / `LOGIN_PASSWORD` no `.env` (hardcode só para teste).
- Se um `debug_*.html` já ficou público, revogue a sessão no Tutory e apague o arquivo.
