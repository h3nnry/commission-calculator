<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\CountryNotFoundByBinException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

final readonly class BinLookupService implements BinLookupInterface
{
    private const CACHE_KEY_PREFIX = 'bin_country_';

    private const CACHE_TTL = 3600;

    public function __construct(
        private string $binApiUrl,
        private Client $client,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    public function getCountryCode(string $bin): string
    {
        $cacheKey = self::CACHE_KEY_PREFIX . $bin;

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        try {
            $response = $this->client->get($this->binApiUrl . $bin);
            $data = json_decode($response->getBody()->getContents(), true);
            $code = $data['country']['alpha2'] ?? null;
        } catch (GuzzleException $e) {
            $code = null;
            $this->logger->error(sprintf('Failed to fetch BIN info for %s: %s', $bin, $e->getMessage()));
        }

        if ($code === null) {
            throw CountryNotFoundByBinException::create($bin);
        }

        $this->cache->set($cacheKey, $code, self::CACHE_TTL);
        return $code;
    }
}
