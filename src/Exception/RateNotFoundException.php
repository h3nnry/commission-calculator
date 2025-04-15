<?php

namespace App\Exception;

use Exception;

class RateNotFoundException extends Exception
{
    private const MESSAGE_FORMAT = 'Exchange rate for currency %s not found.';

    public static function create(string $currency): self
    {
        return new self(
            sprintf(
                self::MESSAGE_FORMAT,
                $currency
            )
        );
    }
}
