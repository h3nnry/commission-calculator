<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\CommissionCalculator;
use App\Service\BinLookupInterface;
use App\Service\ExchangeRateInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 *
 */
class CommissionCalculatorTest extends TestCase
{
    private CommissionCalculator $commissionCalculator;

    private ExchangeRateInterface&MockObject $exchangeRateService;

    private BinLookupInterface&MockObject $binLookupService;

    protected function setUp(): void
    {
        $this->exchangeRateService = $this->createMock(ExchangeRateInterface::class);
        $this->binLookupService = $this->createMock(BinLookupInterface::class);
        $this->commissionCalculator = new CommissionCalculator(
            $this->exchangeRateService,
            $this->binLookupService,
        );
    }

    #[DataProvider('provideCalculatorCases')]
    public function testCalculateCommission(string $bin, string $countryCode, $currency, float $rate, float $amount, string $expected): void
    {
        $this->binLookupService->method('getCountryCode')->willReturn($countryCode);
        $this->exchangeRateService->method('getRate')->willReturn($rate);

        $result = $this->commissionCalculator->calculateCommission($bin, $amount, $currency);

        self::assertSame($result, $expected);
    }

    public function testProcessFileFileNotFound(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->commissionCalculator->processFile('/path/to/nonexistent/file.json');
    }

    public function testProcessFile_InvalidLine(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'commission_test_');
        file_put_contents($tempFile, "not a json\n");

        ob_start();
        $this->commissionCalculator->processFile($tempFile);
        $output = ob_get_clean();

        unlink($tempFile);

        self::assertEmpty($output);
    }

    /**
     * @return array<string, array<string, string|float>>
     */
    public static function provideCalculatorCases(): array
    {
        return [
            'Calculate commission EU country' => [
                'bin' => '45717360',
                'countryCode' => 'FR',
                'currency' => 'USD',
                'amount' => 100.00,
                'rate' => 2.0,
                'expected' => '0.50',
            ],
            'Calculate commission EU country round up' => [
                'bin' => '45717360',
                'countryCode' => 'FR',
                'currency' => 'USD',
                'amount' => 100.22,
                'rate' => 2.0,
                'expected' => '0.51',
            ],
            'Calculate commission NON-EU country' => [
                'bin' => '12345678',
                'countryCode' => 'US',
                'currency' => 'USD',
                'amount' => 200.00,
                'rate' => 1.0,
                'expected' => '4.00',
            ],
            'Calculate commission Zero rate' => [
                'bin' => '99999999',
                'countryCode' => 'DE',
                'currency' => 'XYZ',
                'amount' => 50.00,
                'rate' => 0.0,
                'expected' => '0.00',
            ],
        ];
    }

}
