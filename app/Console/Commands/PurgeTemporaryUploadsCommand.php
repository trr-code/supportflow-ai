<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeTemporaryUploadsCommand extends Command
{
    #[\Override]
    protected $signature = 'uploads:purge-temporary {--execute : Delete expired temporary uploads after owner approval}';

    #[\Override]
    protected $description = 'Remove local Livewire temporary uploads older than 24 hours';

    public function handle(): int
    {
        $diskName = config('livewire.temporary_file_upload.disk') ?: config('filesystems.default');
        $directory = config('livewire.temporary_file_upload.directory') ?: 'livewire-tmp';

        if (! is_string($diskName) || ! is_string($directory)
            || trim($directory, '/\\') === '' || str_contains($directory, '..')
            || config('filesystems.disks.'.$diskName.'.driver') !== 'local') {
            $this->error('Temporary upload cleanup requires a local disk and a dedicated directory.');

            return self::FAILURE;
        }

        $disk = Storage::disk($diskName);
        $cutoff = now()->subDay()->timestamp;
        $count = 0;
        $failed = false;

        foreach ($disk->allFiles($directory) as $path) {
            if (! $disk->exists($path) || $disk->lastModified($path) >= $cutoff) {
                continue;
            }

            if ($this->option('execute') && ! $disk->delete($path)) {
                $failed = true;

                continue;
            }

            $count++;
        }

        $this->info($this->option('execute')
            ? "Removed {$count} expired temporary uploads."
            : "Dry run: {$count} temporary uploads are older than 24 hours. No files changed.");

        if ($failed) {
            $this->error('Some temporary uploads could not be removed; cleanup will retry.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
