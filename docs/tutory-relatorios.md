# Relatórios do Coach (Tutory)

CLI PHP que baixa os relatórios do Coach dos alunos **ativos** no Tutory, **consolida as seções pedidas em um único PDF** e envia por e-mail aos alunos cadastrados no admin (`recebe_email=true`).

No Hostinger compartilhado **não precisa de Node nem de npm**. O PDF é montado com PHP/Dompdf a partir dos HTMLs oficiais. Puppeteer é opcional e fica desligado por padrão.

## O que entra no PDF único

Não é a junção dos cinco PDFs oficiais. O compositor abre cada página do Tutory, espera os gráficos reais e recorta só os blocos abaixo, reusando o CSS/layout moderno do **Desempenho (NOVO)**.

| Origem | Seções extraídas | Fora do consolidado |
|--------|------------------|---------------------|
| **Desempenho (NOVO)** `desempenho` | Cabeçalho do aluno (`.main-header-card`) + cards de métricas (`.metrics-grid`) + as duas colunas Horas de estudo / Performance por Área (último `.two-col-grid`) | Ranking, panorama, progresso mensal, modalidades, progresso por disciplina, insights desse relatório, rodapé |
| **Estudos** `aluno` | Histórico de Metas (`#tabela_estudos`) e Revisões no Período (`#tabela_revisoes`, omitido/avisado se vazio) | Panorama, gráficos de questões, performance por assunto (duplicata) |
| **Horas Líquidas** `horas-liquidas` | Desempenho ao longo do Tempo (`#chart_line_comparativo`) e Histórico (`#tabela_horas_liquidas`) | Pizza por disciplina, progresso por disciplina |
| **Questões** `questoes` | Breve Panorama (`.main-numbers`), gráfico principal (`#chart_questoes_dia`) e Performance por assunto (`#tabela_questoes`) | Bolha, top melhores/piores, evolução por matéria |
| **Progresso do plano** `progresso` | Motivação (`#chart_horas_diarias`) e **Painel de Insights** (`.insights-panel`, em destaque visual) | Progresso principal, panorama, modalidades, desempenho de questões |

Performance por assunto aparece só uma vez, na implementação do relatório de Questões.

## Fluxo

1. `POST /intent/login` → sessão + Bearer (`adminUser.token`)
2. `GET /alunos/consulta?status=ativos` → `data-id` de cada aluno
3. Para **cada modelo** em `RELATORIOS` (mesmo token de geração):
   1. `POST /intent/cadastrar-relatorio-coach` (`alunos[]`, `dt_ini`, `dt_fim`, `agrupamento=dia`)
   2. `GET /documentos/relatorios/{model}?key=...` (HTML oficial; as páginas do Tutory **não são alteradas**)
4. Monta **um** PDF com **PHP/Dompdf + QuickChart** (padrão no Hostinger; Puppeteer só se `TUTORY_PDF_ENGINE=puppeteer`)
5. Reprocessa falhas por aluno (até 3 tentativas)
6. Lista alunos do **admin** (`alunos`) → localiza o PDF `relatorio_consolidado_*` → avalia o desempenho e grava `last_performance` → envia **um único e-mail com 1 anexo** se `recebe_email=true` → **apaga todos os PDFs** da pasta de download (e os `.metricas.json` ao lado). Logs `log_download_*.txt` permanecem.

O renderer individual `scripts/tutory-render-pdf.mjs` continua no repositório para uso pontual. **Não é necessário em produção sem Node.**

## Hostinger / shared hosting (sem npm)

**Não rode `npm install`.** O comando `npm` não existe nesse servidor e **não é necessário**. O PDF consolidado sai com PHP/Dompdf por padrão (`TUTORY_PDF_ENGINE=dompdf`).

```bash
php artisan tutory:baixar-relatorios --periodo=1 --teste
```

Log esperado:

```
PDF com PHP/Dompdf — não usa npm/Node. Ignore "npm: command not found".
Modo: CLI/HTTP → cadastrar-relatorio-coach + 1 PDF consolidado via PHP/Dompdf (TUTORY_PDF_ENGINE=dompdf)
[Giovanna] TUTORY_PDF_ENGINE=dompdf — gerando o consolidado com PHP/Dompdf
```

## Modelos-fonte (`RELATORIOS`)

Definidos em `CoachReportDownloader::RELATORIOS`:

| model (URL) | Nome no menu |
|-------------|--------------|
| `desempenho` | Desempenho |
| `aluno` | Estudos |
| `horas-liquidas` | Horas Líquidas |
| `questoes` | Desempenho em Questões |
| `progresso` | Progresso do plano |

Os cinco modelos são os botões de `#modalGeraRelatorio` em Pesquisar Alunos. O token gerado em `cadastrar-relatorio-coach` vale para todos: muda só o segmento `/documentos/relatorios/{model}`.

## Requisitos

- PHP 8.2+ com `php-gd` (Dompdf)
- Rede para `admin.tutory.com.br` e `quickchart.io` (gráficos no fallback)
- Node.js 18+ com `puppeteer` — **opcional**; só se quiser o compositor fiel ao navegador
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
TUTORY_PDF_ENGINE=dompdf
# NODE_BINARY=
# TUTORY_REPORT_GENERATE_URL=/intent/cadastrar-relatorio-coach
# TUTORY_REPORT_AGRUPAMENTO=dia
```

Em teste, o serviço pode usar credenciais hardcoded. Em produção, use `LOGIN_USER` / `LOGIN_PASSWORD`.

Se `PASTA_DOWNLOAD` estiver vazio, usa `public/pdfs`.

## Uso

```bash
# Regenerar só a Giovanna (quinzena atual):
# período 1 = dias 01–15; período 2 = dia 16 até o último dia do mês
php artisan tutory:baixar-relatorios --periodo=1 --teste
php artisan tutory:baixar-relatorios --periodo=2 --teste
```

Arquivo gerado: `relatorio_consolidado_{Ymd_Hi}_{aluno}_{periodo}.pdf`  
Exemplo: `relatorio_consolidado_20260818_2230_Giovanna_1.pdf`

O envio de e-mail anexa o PDF consolidado mais recente do aluno para o `--periodo` informado. Depois do envio, **todos os PDFs da pasta são apagados**.

Para renderizar um modelo isolado (debug, não usado no e-mail):

```bash
node scripts/tutory-render-pdf.mjs --url "https://admin.tutory.com.br/documentos/relatorios/questoes?key=..." --out /tmp/q.pdf --model questoes
```

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

Em `/admin/desempenho` o mentor edita as **faixas e textos** dos eixos do documento de parâmetros. O e-mail do aluno recebe um bloco por eixo aplicável.

As métricas saem dos HTMLs oficiais de Progresso e Questões (mesmo cálculo de antes) e vão no sidecar `.metricas.json` do PDF consolidado.

| Eixo | Métrica | Origem |
|------|---------|--------|
| Constância na quinzena | `dias_falhados = dias_analisados − dias_estudados` | Progresso → `#chart_horas_diarias` |
| Quantidade total de questões | `total_questoes` | Questões → `.main-numbers` |
| Percentual geral de acertos | `% acertos` (só se ≥ 100 questões) | Questões → `.main-numbers` |
| Percentual por assunto | assuntos com % ≤ 75 | Questões → `#tabela_questoes` |

Faixas padrão (seed):

- **Constância:** 0 excelente · 1–3 bom · 4–10 brigando · ≥11 crítico  
- **Volume:** 0–49 crítico · 50–99 baixo · 100–500 suficiente · >500 alto  
- **% geral:** ≤60 crítico · ≤70 alerta · <80 mediano · <90 muito bom · ≤100 excelente  
- **Assunto:** um único bloco com N bullets (≤60 crítico · ≤75 abaixo da média); texto de acompanhamento após a lista  


Placeholders nos textos: `{NOME}`, `{X}`, `{Y}`, `{Z}`, `{TOTAL_QUESTOES}`, `{PERCENTUAL_ACERTOS}`, `{ASSUNTO}`, `{PERCENTUAL}`.

```bash
php artisan migrate
php artisan db:seed --class=ParametrosDesempenhoSeeder
```

## Segurança

- **Não** salve dumps de HTML do admin em `public/` (o `adminUser.token` vaza).
- Prefira `LOGIN_USER` / `LOGIN_PASSWORD` no `.env` (hardcode só para teste).
- Se um `debug_*.html` já ficou público, revogue a sessão no Tutory e apague o arquivo.
- Os PDFs gerados são apagados da pasta de download **depois do envio** dos e-mails.
