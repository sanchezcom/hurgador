<?php
declare(strict_types=1);

/*
 * Configuración del monitor.
 * Obtén tu API key gratuita en Finnhub:
 * https://finnhub.io/
 */

const DB_FILE = __DIR__ . '/data/stocks.sqlite';
const TICKERS_FILE = __DIR__ . '/tickers.txt';

const FINNHUB_API_KEY = 'PON_AQUI_TU_API_KEY';
const FINNHUB_URL = 'https://finnhub.io/api/v1';

// Compatibilidad con el código anterior que todavía usa los nombres de Alpha Vantage.
const ALPHAVANTAGE_API_KEY = FINNHUB_API_KEY;
const ALPHAVANTAGE_URL = FINNHUB_URL . '/quote';

const ALERT_EMAIL = 'direccion@gmail.com';
const MAIL_FROM = 'direccion@gmail.com';

const DROP_5D = 0.07;   // 7%
const DROP_10D = 0.10;  // 10%
const DROP_20D = 0.15;  // 15%

// Una alerta no vuelve a enviarse para el mismo ticker hasta que
// la cotización haya vuelto por encima de todos los umbrales.
const RESET_5D = 0.03;
const RESET_10D = 0.04;
const RESET_20D = 0.06;

const API_TIMEOUT_SECONDS = 20;


