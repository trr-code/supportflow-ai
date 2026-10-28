<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class PruneLocalLogsCommand extends Command
{
    #[\Override]
    protected $signature = 'logs:prune {--execute : Trim expired or oversized local application and browser logs}';

    #[\Override]
    protected $description = 'Bound local log storage while preserving recent diagnostics and logger compatibility';

    public function handle(): int
    {
        $directory = realpath(storage_path('logs'));
        $count = 0;
        $cutoff = now()->subDays(max(1, (int) config('supportflow.retention.log_days', 14)))->getTimestamp();
        $maxBytes = max(1, (int) config('supportflow.retention.log_max_bytes', 20 * 1024 * 1024));
        $keepBytes = max(0, min($maxBytes - 1, (int) config('supportflow.retention.log_keep_bytes', 10 * 1024 * 1024)));

        if ($directory !== false) {
            foreach (File::files($directory) as $file) {
                $path = $file->getRealPath();

                if ($file->isLink() || $path === false || dirname($path) !== $directory
                    || preg_match('/^(?:laravel|browser)(?:-\d{4}-\d{2}-\d{2})?\.log$/', $file->getFilename()) !== 1) {
                    continue;
                }

                $count += $this->prune($path, $cutoff, $maxBytes, $keepBytes) ? 1 : 0;
            }
        }

        $this->info($this->option('execute')
            ? "Pruned {$count} expired or oversized log files."
            : "Dry run: {$count} log files need pruning. No files changed.");

        return self::SUCCESS;
    }

    private function prune(string $path, int $cutoff, int $maxBytes, int $keepBytes): bool
    {
        $handle = fopen($path, 'r+b');

        if ($handle === false) {
            throw new RuntimeException('A local log could not be opened for maintenance.');
        }

        try {
            if (! flock($handle, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('A local log is busy; cleanup will retry.');
            }

            $stat = fstat($handle);

            if ($stat === false || $stat['size'] === 0) {
                return false;
            }

            $expired = $stat['mtime'] < $cutoff;

            if (! $expired && $stat['size'] <= $maxBytes) {
                return false;
            }

            if (! $this->option('execute')) {
                return true;
            }

            $tail = '';

            if (! $expired && $keepBytes > 0) {
                if (fseek($handle, -$keepBytes, SEEK_END) !== 0) {
                    throw new RuntimeException('A local log could not be read for maintenance.');
                }

                $tail = stream_get_contents($handle, $keepBytes);

                if ($tail === false) {
                    throw new RuntimeException('A local log could not be read for maintenance.');
                }

                $newline = strpos($tail, "\n");
                $tail = $newline === false ? '' : substr($tail, $newline + 1);
            }

            rewind($handle);
            $written = 0;
            $length = strlen($tail);

            while ($written < $length) {
                $bytes = fwrite($handle, substr($tail, $written));

                if ($bytes === false || $bytes === 0) {
                    throw new RuntimeException('A local log could not be trimmed; cleanup will retry.');
                }

                $written += $bytes;
            }

            if (! ftruncate($handle, $length) || ! fflush($handle)) {
                throw new RuntimeException('A local log could not be trimmed; cleanup will retry.');
            }

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
