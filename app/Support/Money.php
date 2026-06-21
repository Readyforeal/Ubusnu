<?php

namespace App\Support;

class Money
{
    public static function format(int $cents): string
    {
        $abs = abs($cents);
        $dollars = intdiv($abs, 100);
        $remainder = $abs % 100;
        $formatted = '$'.number_format($dollars).'.'.str_pad((string) $remainder, 2, '0', STR_PAD_LEFT);

        return $cents < 0 ? '-'.$formatted : $formatted;
    }

    public static function toCents(string $value): int
    {
        $clean = preg_replace('/[^0-9.\-]/', '', $value);

        return (int) round(((float) $clean) * 100);
    }
}
