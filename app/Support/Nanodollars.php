<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * All money in this app is an integer number of nanodollars: 1 = $0.000000001.
 * Pixelforge reports costs to exactly 9 decimals, so integers keep every amount
 * exact and floats never touch money.
 */
final class Nanodollars
{
    public const PER_DOLLAR = 1_000_000_000;

    /** "12.50" -> 12_500_000_000. Rejects anything that isn't a plain decimal string. */
    public static function fromDecimalString(string $dollars): int
    {
        if (preg_match('/^(\d+)(?:\.(\d{1,9}))?$/', $dollars, $matches) !== 1) {
            throw new InvalidArgumentException("Not a dollar amount: {$dollars}");
        }

        $fraction = str_pad($matches[2] ?? '', 9, '0');

        return (int) $matches[1] * self::PER_DOLLAR + (int) $fraction;
    }

    /** 169_080_000 -> "0.169080000", the same format Pixelforge uses. */
    public static function toDecimalString(int $nanos): string
    {
        $sign = $nanos < 0 ? '-' : '';
        $nanos = abs($nanos);

        return sprintf('%s%d.%09d', $sign, intdiv($nanos, self::PER_DOLLAR), $nanos % self::PER_DOLLAR);
    }
}
