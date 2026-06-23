<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackupSqlite extends Command
{
    protected $signature = 'app:backup-sqlite {--keep=30}';

    protected $description = 'Snapshot the SQLite database to a gzipped file in the backups directory.';

    public function handle(): int
    {
        $driver = config('database.default');
        if ($driver !== 'sqlite') {
            $this->info("Skipping backup — configured database is not a sqlite connection (got: {$driver}).");

            return self::SUCCESS;
        }

        $source = (string) config('database.connections.sqlite.database');
        if (! is_file($source)) {
            $this->warn("Skipping backup — sqlite file not found at {$source}.");

            return self::SUCCESS;
        }

        $backupsDir = getenv('BACKUPS_DIR') ?: '/var/www/backups';
        if (! is_dir($backupsDir)) {
            @mkdir($backupsDir, 0755, true);
        }
        if (! is_dir($backupsDir) || ! is_writable($backupsDir)) {
            $this->error("Backups dir is not writable: {$backupsDir}");

            return self::FAILURE;
        }

        $timestamp = date('Y-m-d-His');
        $tempPath = $backupsDir.'/.tmp-'.$timestamp.'.sqlite';
        $finalPath = $backupsDir.'/ubusnu-'.$timestamp.'.sqlite.gz';

        try {
            $pdo = new \PDO('sqlite:'.$source);
            $pdo->exec("VACUUM INTO '".addslashes($tempPath)."'");
            unset($pdo);

            $bytes = (string) file_get_contents($tempPath);
            file_put_contents($finalPath, gzencode($bytes, 6));
            @unlink($tempPath);

            $size = round(filesize($finalPath) / 1024 / 1024, 2);

            $keep = max(1, (int) $this->option('keep'));
            $pruned = $this->prune($backupsDir, $keep);

            $this->info('Backup '.basename($finalPath)." written ({$size} MB). Kept {$keep}; pruned {$pruned}.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            @unlink($tempPath);
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function prune(string $dir, int $keep): int
    {
        $files = glob($dir.'/ubusnu-*.sqlite.gz') ?: [];
        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        $toPrune = array_slice($files, $keep);
        foreach ($toPrune as $path) {
            @unlink($path);
        }

        return count($toPrune);
    }
}
