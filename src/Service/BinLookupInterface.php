<?php

declare(strict_types=1);

namespace App\Service;

interface BinLookupInterface
{
    public function getCountryCode(string $bin): ?string;
}
