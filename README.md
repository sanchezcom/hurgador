# hurgador - PHP + SQLite + finnhub

Buscador de valores en bolsa y notificador si hay bajadas interesantes

## Requisitos:
- PHP CLI 8.x
- PDO SQLite / pdo_sqlite
- cURL
- función mail() configurada
- API key gratuita de finnhub

## Desplieuge

1. Copia config_example.php a config.php y configura

1. Edita config.php:
   - FINNHUB_API_KEY
   - ALERT_EMAIL
   - MAIL_FROM

1. Edita tickers.txt con símbolos separados por coma.

1. Crea la BBDD:
   php db_create.php

1. Ejecuta manualmente:
   php monitor.php

1. Cron (ejemplo, lunes-viernes a las 19:00):
   0 19 * * 1-5 /usr/bin/php /ruta/stock-monitor/monitor.php >> /ruta/stock-monitor/monitor.log 2>&1

## Notas:
- La versión inicial usa GLOBAL_QUOTE de Alpha Vantage.
- Alpha Vantage requiere una API key y el endpoint gratuito tiene límites.
- El sistema guarda el cierre/precio diario en SQLite y necesita acumular datos
  antes de poder calcular ventanas de 5/10/20 sesiones.
- No compra ni vende. Solo genera alertas.
- La alerta se dispara inicialmente con caídas de 7%, 10% o 15% en 5, 10 o 20
  sesiones respectivamente.
