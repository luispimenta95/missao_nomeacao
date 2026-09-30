#!/usr/bin/env bash
# Cria na Hostinger o cron de todo minuto, se HOSTINGER_API_TOKEN existir.
# O PHP (America/Sao_Paulo) é quem decide se o minuto é o horário do job.
set -euo pipefail

TOKEN="${HOSTINGER_API_TOKEN:-}"
USER_NAME="${HOSTINGER_USER:-}"

if [ -z "$TOKEN" ] || [ -z "$USER_NAME" ]; then
  echo "HOSTINGER_API_TOKEN ausente. O cron de todo minuto não foi criado pela API."
  echo "Cadastre no hPanel → Cron Jobs → Custom, uma vez por minuto:"
  echo "/bin/sh /home/USUARIO/domains/missaonomeacao.com.br/public_html/server/scripts/tutory-scheduler.sh"
  exit 0
fi

CMD="/bin/sh /home/${USER_NAME}/domains/missaonomeacao.com.br/public_html/server/scripts/tutory-scheduler.sh"
API="https://developers.hostinger.com/api/hosting/v1/accounts/${USER_NAME}/cron-jobs"

EXISTING="$(curl -fsS -H "Authorization: Bearer ${TOKEN}" -H "Accept: application/json" "$API")"
if printf '%s' "$EXISTING" | grep -F 'tutory-scheduler.sh' >/dev/null; then
  echo "Cron tutory-scheduler.sh já existe."
  exit 0
fi

BODY="$(jq -n --arg time '* * * * *' --arg command "$CMD" '{time:$time, command:$command}')"
curl -fsS -X POST "$API" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "$BODY"
echo
echo "Cron de todo minuto criado."
