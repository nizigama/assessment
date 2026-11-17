<?php

declare(strict_types=1);

namespace App\Enums;

enum CreditTransactionType: string
{
    /** Credits bought by the user. */
    case Purchase = 'purchase';
    /** The maximum possible price, set aside when a generation is submitted. */
    case Hold = 'hold';
    /** The hold given back once the generation finishes (succeeded or failed). */
    case Release = 'release';
    /** The exact price of a succeeded generation. */
    case Charge = 'charge';
}
