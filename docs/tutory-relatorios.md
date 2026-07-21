# Relatórios do Coach (Tutory)

CLI PHP **somente HTTP** (sem Selenium/Firefox) que baixa o Relatório do Coach dos alunos **ativos**.

## Fluxo

1. `POST /intent/login` → sessão + Bearer (`adminUser.token`)
2. `GET /alunos/consulta?status=ativos` → `data-id` de cada aluno
3. `POST /intent/cadastrar-relatorio-coach` (`alunos[]`, `dt_ini`, `dt_fim`, `agrupamento=mes`)
4. `GET /documentos/relatorios/questoes?key=...`
5. PDF via **Dompdf** (gráficos renderizados com QuickChart a partir do Chart.js da página)
6. Reprocessa falhas (até 3 tentativas)

## Requisitos

- PHP 8.2+ com extensão **gd** (`php-gd`) — necessária para imagens no PDF
- Credenciais no `.env`
- Rede para `admin.tutory.com.br` e `quickchart.io`

## `.env`

```env
LOGIN_URL=https://admin.tutory.com.br/login
LOGIN_USER=
LOGIN_PASSWORD=
# Preferível fora de public/ (evita expor HTML/token)
PASTA_DOWNLOAD=
TIMEOUT=60
# Opcional:
# TUTORY_REPORT_GENERATE_URL=/intent/cadastrar-relatorio-coach
# TUTORY_REPORT_MODEL=questoes
```

Se `PASTA_DOWNLOAD` estiver vazio, usa `storage/app/tutory-relatorios`.

## Uso

```bash
php scripts/baixar_relatorios_tutory.php --periodo=1
php scripts/baixar_relatorios_tutory.php --periodo=2 --teste

# ou
php artisan tutory:baixar-relatorios --periodo=1 --teste
```

## Segurança

- **Não** salve dumps de HTML do admin em `public/` (o `adminUser.token` vaza).
- Use sempre `LOGIN_USER` / `LOGIN_PASSWORD` no `.env` (nunca hardcode no código).
- Se um `debug_*.html` já ficou público, revogue a sessão no Tutory e apague o arquivo.
