<?php

namespace App\Support;

class Stats
{
    /**
     * @param  array<int, float|int>  $values
     */
    public static function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $count = count($values);
        $mid = (int) floor($count / 2);

        return $count % 2 === 1
            ? (float) $values[$mid]
            : (($values[$mid - 1] + $values[$mid]) / 2.0);
    }

    /**
     * @param  array<int, float|int>  $values
     */
    public static function mean(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        return array_sum($values) / count($values);
    }

    /**
     * @param  array<int, float|int>  $values
     */
    public static function stdDev(array $values): ?float
    {
        $count = count($values);
        if ($count < 2) {
            return null;
        }
        $mean = self::mean($values);
        $sumSq = 0.0;
        foreach ($values as $v) {
            $sumSq += ($v - $mean) ** 2;
        }

        return sqrt($sumSq / ($count - 1));
    }
}
