<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\RateNotFoundException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

final readonly class ExchangeRateService implements ExchangeRateInterface
{
    private const CACHE_KEY = 'exchange_rates';

    private const CACHE_TTL = 3600;

    private const DEFAULT_CURRENCY = 'EUR';

    public function __construct(
        private string          $apiUrl,
        private Client          $client,
        private CacheInterface  $cache,
        private LoggerInterface $logger,
    ) {
    }

    public function getRate(string $currency): float
    {
        if ($currency === self::DEFAULT_CURRENCY) {
            return 1.0;
        }

        $rates = $this->getRates();

        if (!isset($rates[$currency])) {
            throw RateNotFoundException::create($currency);
        }

        return $rates[$currency];
    }

    public function getRates(): array
    {

        if ($this->cache->has(self::CACHE_KEY)) {
            return $this->cache->get(self::CACHE_KEY);
        }

        try {
            $response = $this->client->get($this->apiUrl);
            $data = json_decode($response->getBody()->getContents(), true);
            $rates = $data['rates'] ?? [];

            if (!empty($rates)) {
                $this->cache->set(self::CACHE_KEY, $rates, self::CACHE_TTL);
            }

            return $rates;
        } catch (GuzzleException $e) {
            $this->logger->error("Failed to fetch exchange rates: " . $e->getMessage());
            return [];
        }
    }
}
