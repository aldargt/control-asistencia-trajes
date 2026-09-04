<?php

namespace App\Support;

class Duration
{
    public static function human(?int $minutes, bool $signed = false): string
    {
        if ($minutes === null) {
            return 'No disponible';
        }
        $sign = $signed ? ($minutes > 0 ? '+' : ($minutes < 0 ? '−' : '')) : '';
        $absolute = abs($minutes);

        return $sign.intdiv($absolute, 60).' h '.str_pad((string) ($absolute % 60), 2, '0', STR_PAD_LEFT).' min';
    }
}
