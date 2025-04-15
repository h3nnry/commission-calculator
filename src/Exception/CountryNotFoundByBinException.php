<?php

namespace App\Exception;

use Exception;

class CountryNotFoundByBinException extends Exception
{
    private const MESSAGE_FORMAT = 'Failed to fetch country code for BIN: %s';

    public static function create(string $bin): self
    {
        return new self(
            sprintf(
                self::MESSAGE_FORMAT,
                $bin
            )
        );
    }
}
