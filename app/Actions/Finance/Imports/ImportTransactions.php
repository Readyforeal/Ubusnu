<?php

namespace App\Actions\Finance\Imports;

use App\Models\Account;
use App\Models\ImportBatch;
use App\Models\Transaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ImportTransactions
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __invoke(Account $account, array $rows, int $userId, string $filename): ImportBatch
    {
        return DB::transaction(function () use ($account, $rows, $userId, $filename) {
            $batch = ImportBatch::create([
                'account_id' => $account->id,
                'user_id' => $userId,
                'filename' => $filename,
                'row_count' => count($rows),
                'imported_count' => 0,
                'skipped_duplicate_count' => 0,
                'error_count' => 0,
            ]);

            $imported = 0;
            $skipped = 0;
            $errors = 0;

            foreach ($rows as $row) {
                if ($row['status'] === 'duplicate') {
                    $skipped++;

                    continue;
                }

                if ($row['status'] === 'error') {
                    $errors++;

                    continue;
                }

                try {
                    Transaction::create([
                        'account_id' => $account->id,
                        'occurred_on' => $row['occurred_on'],
                        'description' => $row['description'],
                        'amount_cents' => $row['amount_cents'],
                        'category_id' => $row['category_id'] ?? null,
                        'dedup_hash' => $row['dedup_hash'],
                        'import_batch_id' => $batch->id,
                        'source' => 'import',
                    ]);
                    $imported++;
                } catch (UniqueConstraintViolationException) {
                    $errors++;
                }
            }

            $batch->update([
                'imported_count' => $imported,
                'skipped_duplicate_count' => $skipped,
                'error_count' => $errors,
            ]);

            return $batch->fresh();
        });
    }
}
