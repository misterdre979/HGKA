<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'hgka:backup';

    protected $description = 'Create a compressed database backup in storage/app/private/backups and prune old ones';

    public function handle(): int
    {
        $connection = config('database.default');
        $cfg = config("database.connections.$connection");

        Storage::disk('local')->makeDirectory('backups');
        $dir = Storage::disk('local')->path('backups');
        $stamp = date('Ymd-His');

        if ($cfg['driver'] === 'sqlite') {
            if (! is_file($cfg['database'])) {
                $this->error('SQLite database file not found.');

                return self::FAILURE;
            }
            copy($cfg['database'], "$dir/hgka-$stamp.sqlite");
        } elseif ($cfg['driver'] === 'mysql' || $cfg['driver'] === 'mariadb') {
            $file = "$dir/hgka-$stamp.sql.gz";
            $process = new Process([
                'mysqldump', '--single-transaction', '--routines', '--no-tablespaces',
                '-h', (string) $cfg['host'], '-P', (string) $cfg['port'], '-u', (string) $cfg['username'], (string) $cfg['database'],
            ], null, ['MYSQL_PWD' => (string) $cfg['password']]); // password via env, never on the command line
            $process->setTimeout(600);

            $gz = gzopen($file, 'wb9');
            $process->run(function ($type, $buffer) use ($gz) {
                if ($type === Process::OUT) {
                    gzwrite($gz, $buffer);
                }
            });
            gzclose($gz);

            if (! $process->isSuccessful()) {
                @unlink($file);
                $this->error('mysqldump failed: '.trim($process->getErrorOutput()));

                return self::FAILURE;
            }
        } else {
            $this->error("Unsupported database driver: {$cfg['driver']}");

            return self::FAILURE;
        }

        $files = collect(Storage::disk('local')->files('backups'))->sortDesc()->values();
        $files->slice((int) config('hgka.backup_keep', 14))->each(fn ($f) => Storage::disk('local')->delete($f));

        $this->info("Backup created: hgka-$stamp");

        return self::SUCCESS;
    }
}
