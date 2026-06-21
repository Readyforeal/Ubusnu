<?php

namespace App\Actions\Finance\Imports;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\TransactionHash;
use Carbon\CarbonImmutable;

class ParseCsvForPreview
{
    private ?Category $transferCategory = null;

    /**
     * @param  array<string, mixed>  $profile
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(Account $account, string $path, array $profile): array
    {
        $delimiter = $profile['delimiter'] ?? ',';
        $hasHeader = (bool) ($profile['has_header'] ?? true);
        $dateFormat = $profile['date_format'];

        $this->transferCategory = Category::where('name', 'Transfer')->first();

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $firstRow = fgetcsv($handle, 0, $delimiter);
        if ($firstRow === false) {
            fclose($handle);

            return [];
        }

        $dateIdx = $this->resolveColumnIndex($profile['date_column'], $firstRow, $hasHeader);
        $descIdx = $this->resolveColumnIndex($profile['description_column'], $firstRow, $hasHeader);
        $amountIdx = $this->resolveColumnIndex($profile['amount_column'], $firstRow, $hasHeader);
        $expectedFieldCount = count($firstRow);

        $rows = [];

        if (! $hasHeader) {
            $rows[] = $this->safeProcessRow($account, $firstRow, $expectedFieldCount, $dateIdx, $dateFormat, $descIdx, $amountIdx);
        }

        while (($raw = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $this->safeProcessRow($account, $raw, $expectedFieldCount, $dateIdx, $dateFormat, $descIdx, $amountIdx);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<int, string>  $firstRow
     */
    private function resolveColumnIndex(string|int $colRef, array $firstRow, bool $hasHeader): int
    {
        if ($hasHeader) {
            $idx = array_search((string) $colRef, $firstRow, true);

            return $idx === false ? -1 : (int) $idx;
        }

        return (int) $colRef;
    }

    /**
     * @param  array<int, string>  $raw
     * @return array<string, mixed>
     */
    private function safeProcessRow(Account $account, array $raw, int $expectedFieldCount, int $dateIdx, string $dateFormat, int $descIdx, int $amountIdx): array
    {
        if (count($raw) !== $expectedFieldCount) {
            return [
                'occurred_on' => null,
                'description' => implode(',', (array) $raw),
                'amount_cents' => null,
                'status' => 'error',
                'error' => 'Malformed CSV row (field count mismatch)',
            ];
        }

        try {
            return $this->processRow($account, $raw, $dateIdx, $dateFormat, $descIdx, $amountIdx);
        } catch (\Throwable) {
            return [
                'occurred_on' => null,
                'description' => implode(',', (array) $raw),
                'amount_cents' => null,
                'status' => 'error',
                'error' => 'Malformed CSV row (parse failure)',
            ];
        }
    }

    /**
     * @param  array<int, string>  $raw
     * @return array<string, mixed>
     */
    private function processRow(Account $account, array $raw, int $dateIdx, string $dateFormat, int $descIdx, int $amountIdx): array
    {
        $rawDate = $raw[$dateIdx] ?? null;
        $rawDesc = trim((string) ($raw[$descIdx] ?? ''));
        $rawAmount = $raw[$amountIdx] ?? null;

        try {
            $occurredOn = CarbonImmutable::createFromFormat($dateFormat, (string) $rawDate);
            if (! $occurredOn) {
                throw new \RuntimeException('Invalid date');
            }
            $occurredOn = $occurredOn->toDateString();
        } catch (\Throwable) {
            return [
                'occurred_on' => null,
                'description' => $rawDesc,
                'amount_cents' => null,
                'status' => 'error',
                'error' => "Could not parse date '{$rawDate}'",
            ];
        }

        $cleanAmount = preg_replace('/[^0-9.\-]/', '', (string) $rawAmount);
        if ($cleanAmount === '' || ! is_numeric($cleanAmount)) {
            return [
                'occurred_on' => $occurredOn,
                'description' => $rawDesc,
                'amount_cents' => null,
                'status' => 'error',
                'error' => "Could not parse amount '{$rawAmount}'",
            ];
        }
        $amountCents = (int) round(((float) $cleanAmount) * 100);

        $hash = TransactionHash::for($account->id, $occurredOn, $amountCents, $rawDesc);
        $duplicate = Transaction::query()
            ->where('account_id', $account->id)
            ->where('dedup_hash', $hash)
            ->first();

        $categoryId = $this->matchTransferCategory($rawDesc);

        return [
            'occurred_on' => $occurredOn,
            'description' => $rawDesc,
            'amount_cents' => $amountCents,
            'dedup_hash' => $hash,
            'category_id' => $categoryId,
            'status' => $duplicate ? 'duplicate' : 'new',
            'duplicate_of' => $duplicate?->id,
        ];
    }

    private function matchTransferCategory(string $description): ?int
    {
        $transfer = $this->transferCategory;
        if (! $transfer) {
            return null;
        }

        $lower = mb_strtolower($description);
        foreach ($transfer->keywordList() as $keyword) {
            if ($keyword && str_contains($lower, $keyword)) {
                return $transfer->id;
            }
        }

        return null;
    }
}
