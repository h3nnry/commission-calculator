<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exception\RateNotFoundException;
use App\Service\ExchangeRateService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 *
 */
class ExchangeRateServiceTest extends TestCase
{
    private const SERVICE_URL = 'https://api.exchangeratesapi.io/latest';

    private ExchangeRateService $service;

    private Client&MockObject $client;
    private CacheInterface&MockObject $cache;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new ExchangeRateService(
            self::SERVICE_URL,
            $this->client,
            $this->cache,
            $this->logger,
        );
    }

    public function testReturnsDefaultRateForEuro(): void
    {
        self::assertSame(1.0, $this->service->getRate('EUR'));
    }

    #[DataProvider('provideRateCases')]
    public function testReturnsRateFromCache(string $currency, array $rates, float $result): void
    {
        $this->cache
            ->expects(self::once())
            ->method('has')
            ->with('exchange_rates')
            ->willReturn(true);
        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with('exchange_rates')
            ->willReturn($rates);

        self::assertSame($result, $this->service->getRate($currency));
    }

    public function testThrowsExceptionIfRateNotFound(): void
    {
        $response = new Response(200, [], json_encode(['rates' => ['JPY' => 128.56]]));

        $this->cache->method('has')->willReturn(false);
        $this->client->method('get')->willReturn($response);

        $this->expectException(RateNotFoundException::class);
        $this->service->getRate('AUD');
    }

    public function testReturnsEmptyArrayOnRequestFailure(): void
    {
        $this->cache->method('has')->willReturn(false);

        $this->client->method('get')->willThrowException(
            new RequestException('Connection error', $this->createMock(RequestInterface::class))
        );

        $this->logger->expects(self::once())->method('error');

        self::assertSame([], $this->service->getRates());
    }

    /**
     * @return array<string, array<string, string|float|array<string, float>>>
     */
    public static function provideRateCases(): array
    {
        return [
            'get USD' => [
                'currency' => 'USD',
                'rates' => ['USD' => 1.2, 'JPY' => 1.5, 'PLN' => 1.8],
                'result' => 1.2,
            ],
            'get PLN' => [
                'currency' => 'PLN',
                'rates' => ['USD' => 1.2, 'JPY' => 1.5, 'PLN' => 1.8],
                'result' => 1.8,
            ],
        ];
    }

}
