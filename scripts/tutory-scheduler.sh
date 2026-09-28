#!/bin/sh
# Cron do hPanel (Web/Cloud não tem crontab pelo SSH).
# Tipo: Custom. Uma vez por minuto.
# Comando: /bin/sh /home/USUARIO/domains/missaonomeacao.com.br/public_html/server/scripts/tutory-scheduler.sh
cd "$(dirname "$0")/.." || exit 1
php artisan schedule:run >> storage/logs/schedule-run.log 2>&1
