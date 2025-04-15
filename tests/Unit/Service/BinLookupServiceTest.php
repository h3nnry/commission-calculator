<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exception\CountryNotFoundByBinException;
use App\Service\BinLookupService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 *
 */
class BinLookupServiceTest extends TestCase
{
    private const SERVICE_URL = 'https://lookup.binlist.net/';

    private const BIN = '123456';

    private const COUNTRY_CODE = 'DE';
    private BinLookupService $service;

    private Client&MockObject $client;
    private CacheInterface&MockObject $cache;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new BinLookupService(
            self::SERVICE_URL,
            $this->client,
            $this->cache,
            $this->logger,
        );
    }

    public function testReturnsCountryCodeFromCache(): void
    {

        $this->cache
            ->expects(self::once())
            ->method('has')
            ->with('bin_country_' . self::BIN)
            ->willReturn(true);
        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with('bin_country_' . self::BIN)
            ->willReturn(self::COUNTRY_CODE);


        $result = $this->service->getCountryCode(self::BIN);
        self::assertSame(self::COUNTRY_CODE, $result);
    }

    public function testFetchesAndCachesCountryCode(): void
    {

        $response = new Response(200, [], json_encode(['country' => ['alpha2' => self::COUNTRY_CODE]]));

        $this->cache->method('has')->willReturn(false);
        $this->client
            ->expects(self::once())
            ->method('get')
            ->with(self::SERVICE_URL . self::BIN)
            ->willReturn($response);
        $this->cache
            ->expects(self::once())
            ->method('set')
            ->with('bin_country_' . self::BIN, self::COUNTRY_CODE, 3600);

        $result = $this->service->getCountryCode(self::BIN);
        self::assertSame(self::COUNTRY_CODE, $result);
    }

    public function testThrowsExceptionAndLogsErrorOnFailure(): void
    {
        $this->cache->method('has')->willReturn(false);

        $exception = new RequestException("Network error", $this->createMock(RequestInterface::class));
        $this->client->method('get')->willThrowException($exception);

        $this->logger->expects(self::once())->method('error');

        $this->expectException(CountryNotFoundByBinException::class);

        $this->service->getCountryCode(self::BIN);
    }

    public function testThrowsExceptionWhenAlpha2Missing(): void
    {
        $response = new Response(200, [], json_encode(['country' => []]));

        $this->cache->method('has')->willReturn(false);
        $this->client->method('get')->willReturn($response);

        $this->expectException(CountryNotFoundByBinException::class);

        $this->service->getCountryCode(self::BIN);
    }
}
