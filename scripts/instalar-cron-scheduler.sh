#!/usr/bin/env bash
# Tenta gravar o cron de todo minuto. O PHP só executa o job no minuto
# de America/Sao_Paulo. No plano Web/Cloud o binário crontab não existe.
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
  echo "Sem esse cron, um tick atrasado não executa o horário que já passou." >&2
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
