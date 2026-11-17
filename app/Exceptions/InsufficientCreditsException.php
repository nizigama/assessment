<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\Nanodollars;
use RuntimeException;

final class InsufficientCreditsException extends RuntimeException
{
    public function __construct(int $balanceNanos, int $neededNanos)
    {
        parent::__construct(sprintf(
            'Not enough credits: this generation can cost up to $%s and your balance is $%s. Buy credits to continue.',
            rtrim(rtrim(Nanodollars::toDecimalString($neededNanos), '0'), '.'),
            rtrim(rtrim(Nanodollars::toDecimalString($balanceNanos), '0'), '.'),
        ));
    }
}
