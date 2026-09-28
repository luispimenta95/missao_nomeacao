#!/usr/bin/env bash
# Tenta gravar o cron do scheduler. No plano Web/Cloud da Hostinger o
# binário crontab não existe; nesse caso o script só imprime o comando
# do hPanel. A visita ao site cobre a sincronização enquanto o cron não existe.
set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
mkdir -p "$APP_DIR/storage/logs"

PHP_BIN="$(command -v php || true)"
if [ -z "$PHP_BIN" ]; then
  echo "ERRO: php não está no PATH; cron não instalado." >&2
  exit 1
fi

if ! command -v crontab >/dev/null 2>&1; then
  echo "AVISO: este plano da Hostinger não tem crontab pelo SSH." >&2
  echo "No hPanel → Cron Jobs → Custom, uma vez por minuto, use:" >&2
  echo "/bin/sh $APP_DIR/scripts/tutory-scheduler.sh" >&2
  echo "Enquanto o cron não existir, uma visita ao site sincroniza os alunos depois da resposta." >&2
  exit 0
fi

MARK="# missao-nomeacao-scheduler"
LINE="* * * * * cd $APP_DIR && $PHP_BIN artisan schedule:run >> $APP_DIR/storage/logs/schedule-run.log 2>&1 $MARK"

CURRENT="$(crontab -l 2>/dev/null || true)"
FILTERED="$(printf '%s\n' "$CURRENT" | grep -v 'missao-nomeacao-scheduler' | grep -v 'missaonomeacao.com.br.*schedule:run' | grep -v 'tutory:executar-agendados' || true)"
{
  printf '%s\n' "$FILTERED" | sed '/^$/d'
  printf '%s\n' "$LINE"
} | crontab -

echo "Cron instalado: $LINE"
