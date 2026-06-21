<?php

namespace App\Support;

class TransactionHash
{
    public static function for(int $accountId, string $occurredOn, int $amountCents, string $description): string
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/', ' ', $description)));

        return hash('sha256', $accountId.'|'.$occurredOn.'|'.$amountCents.'|'.$normalized);
    }
}
