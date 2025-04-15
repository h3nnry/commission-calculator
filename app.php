<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use App\CommissionCalculator;
use App\Service\BinLookupService;
use App\Service\ExchangeRateService;
use Dotenv\Dotenv;
use GuzzleHttp\Client;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

// Load env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Setup dependencies
$client = new Client();
$cache = new Psr16Cache(new FilesystemAdapter('app_cache', 0, __DIR__ . '/var/cache'));

$logger = new Logger('app');
$logger->pushHandler(new StreamHandler($_ENV['LOG_FILE'] ?? __DIR__ . '/var/log/app.log'));

$exchangeService = new ExchangeRateService(
    apiUrl: $_ENV['EXCHANGE_RATE_URL'],
    client: $client,
    cache: $cache,
    logger: $logger,
);

$binLookupService = new BinLookupService(
    binApiUrl: $_ENV['BIN_LOOKUP_URL'],
    client: $client,
    cache: $cache,
    logger: $logger,
);

$calculator = new CommissionCalculator($exchangeService, $binLookupService);
$calculator->processFile($argv[1] ?? '');
