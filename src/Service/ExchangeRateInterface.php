<?php

declare(strict_types=1);

namespace App\Service;

interface ExchangeRateInterface
{
    public function getRate(string $currency): float;
}
