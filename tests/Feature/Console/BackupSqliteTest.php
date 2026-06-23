<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->backupDir = sys_get_temp_dir().'/ubusnu-backups-'.uniqid();
    @mkdir($this->backupDir, 0755, true);
    putenv('BACKUPS_DIR='.$this->backupDir);

    $this->sourceDb = sys_get_temp_dir().'/ubusnu-test-'.uniqid().'.sqlite';
    $pdo = new PDO('sqlite:'.$this->sourceDb);
    $pdo->exec('CREATE TABLE marker (id INTEGER PRIMARY KEY, name TEXT)');
    $pdo->exec("INSERT INTO marker (name) VALUES ('hello')");
    unset($pdo);

    config(['database.default' => 'sqlite']);
    config(['database.connections.sqlite.database' => $this->sourceDb]);
});

afterEach(function () {
    File::deleteDirectory($this->backupDir);
    @unlink($this->sourceDb);
    putenv('BACKUPS_DIR');
    config(['database.default' => 'sqlite']);
});

it('writes a timestamped .sqlite.gz file into the backup dir', function () {
    $this->artisan('app:backup-sqlite')->assertExitCode(0);

    $files = glob($this->backupDir.'/ubusnu-*.sqlite.gz');
    expect($files)->toHaveCount(1);
});

it('produces a valid gzip containing a SQLite header', function () {
    $this->artisan('app:backup-sqlite')->assertExitCode(0);

    $file = glob($this->backupDir.'/ubusnu-*.sqlite.gz')[0];
    $decompressed = gzdecode((string) file_get_contents($file));

    expect(substr($decompressed, 0, 16))->toStartWith('SQLite format 3');
});

it('prunes older files past --keep', function () {
    for ($i = 0; $i < 4; $i++) {
        $path = $this->backupDir.'/ubusnu-old-'.$i.'.sqlite.gz';
        file_put_contents($path, gzencode('SQLite format 3'."\0".str_repeat("\0", 16)));
        touch($path, time() - ($i + 1) * 3600);
    }

    $this->artisan('app:backup-sqlite', ['--keep' => 2])->assertExitCode(0);

    $files = glob($this->backupDir.'/ubusnu-*.sqlite.gz');
    expect($files)->toHaveCount(2);
});

it('exits 0 with a friendly note when the connection is not sqlite', function () {
    config(['database.default' => 'pgsql']);

    $this->artisan('app:backup-sqlite')
        ->expectsOutputToContain('not a sqlite')
        ->assertExitCode(0);
});
