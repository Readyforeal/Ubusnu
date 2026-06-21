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
        $dateCol = $profile['date_column'];
        $dateFormat = $profile['date_format'];
        $descCol = $profile['description_column'];
        $amountCol = $profile['amount_column'];

        $this->transferCategory = Category::where('name', 'Transfer')->first();

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $header = null;
        $rows = [];

        while (($raw = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($hasHeader && $header === null) {
                $header = $raw;

                continue;
            }

            try {
                $assoc = $hasHeader ? array_combine($header, $raw) : $raw;
                $rows[] = $this->processRow($account, $assoc, $dateCol, $dateFormat, $descCol, $amountCol);
            } catch (\Throwable $e) {
                $rows[] = [
                    'occurred_on' => null,
                    'description' => implode(',', (array) $raw),
                    'amount_cents' => null,
                    'status' => 'error',
                    'error' => 'Malformed CSV row (field count mismatch or parse failure)',
                ];
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, string>  $assoc
     * @return array<string, mixed>
     */
    private function processRow(Account $account, array $assoc, string $dateCol, string $dateFormat, string $descCol, string $amountCol): array
    {
        $rawDate = $assoc[$dateCol] ?? null;
        $rawDesc = trim((string) ($assoc[$descCol] ?? ''));
        $rawAmount = $assoc[$amountCol] ?? null;

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
