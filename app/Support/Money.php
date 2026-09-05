<?php

namespace App\Support;

class Money
{
    public static function human(?int $cents): string
    {
        if ($cents === null) {
            return 'No disponible';
        }

        return 'Bs '.number_format($cents / 100, 2, ',', '.');
    }
}
