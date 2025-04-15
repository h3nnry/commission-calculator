<?php

declare(strict_types=1);

namespace App;

use App\Enum\EuCountryEnum;
use App\Exception\CountryNotFoundByBinException;
use App\Exception\RateNotFoundException;
use App\Service\BinLookupInterface;
use App\Service\ExchangeRateInterface;

use const FILE_IGNORE_NEW_LINES;
use const FILE_SKIP_EMPTY_LINES;

use InvalidArgumentException;

final readonly class CommissionCalculator
{
    public function __construct(
        private ExchangeRateInterface $exchangeService,
        private BinLookupInterface $binLookupService,
    ) {
    }

    public function processFile(string $filename): void
    {
        if (!file_exists($filename)) {
            throw new InvalidArgumentException("File not found: $filename");
        }

        $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $transaction = json_decode($line, true);
            if (!$transaction || !isset($transaction['bin'], $transaction['amount'], $transaction['currency'])) {
                continue;
            }

            try {
                echo $this->calculateCommission($transaction['bin'], (float)$transaction['amount'], $transaction['currency']);
            } catch (CountryNotFoundByBinException | RateNotFoundException $e) {
                echo $e->getMessage() . "\n";
            }

        }
    }

    public function calculateCommission(string $bin, float $amount, string $currency): string
    {
        $countryCode = $this->binLookupService->getCountryCode($bin);

        $isEu = EuCountryEnum::isEuCountry($countryCode);
        $rate = $this->exchangeService->getRate($currency);

        if ($rate === 0.0) {
            return '0.00';
        }

        $amountInEur = $amount / $rate;
        $fee = $isEu ? 0.01 : 0.02;

        return number_format(ceil($amountInEur * $fee * 100) / 100, 2, '.', '');
    }
}
