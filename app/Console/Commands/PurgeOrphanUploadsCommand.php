<?php

namespace App\Console\Commands;

use App\Models\SupplierInvoice;
use App\Models\WorkspaceDocument;
use App\Services\UploadAdmission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PurgeOrphanUploadsCommand extends Command
{
    #[\Override]
    protected $signature = 'uploads:purge-orphans {--execute : Delete old generated upload files with no owning record}';

    #[\Override]
    protected $description = 'Remove abandoned generated invoice and workspace files after a safety grace period';

    public function handle(UploadAdmission $admission): int
    {
        $count = 0;
        $cutoff = now()->subHours(max(24, (int) config('supportflow.retention.orphan_upload_hours', 24)))->getTimestamp();

        foreach (['local' => 'supplier-invoices', 'knowledge' => ''] as $diskName => $directory) {
            if (config('filesystems.disks.'.$diskName.'.driver') !== 'local') {
                $this->error('Orphan upload cleanup supports local disks only.');

                return self::FAILURE;
            }

            $disk = Storage::disk($diskName);

            foreach ($disk->allFiles($directory) as $path) {
                if (! $this->isGeneratedPath($diskName, $path)) {
                    continue;
                }

                $count += $admission->store(function () use ($disk, $diskName, $path, $cutoff): int {
                    if (! $disk->exists($path) || $disk->lastModified($path) >= $cutoff) {
                        return 0;
                    }

                    $referenced = $diskName === 'local'
                        ? SupplierInvoice::query()->where('storage_path', $path)->exists()
                        : WorkspaceDocument::query()->where('storage_path', $path)->exists();

                    if ($referenced) {
                        return 0;
                    }

                    if ($this->option('execute') && ! $disk->delete($path)) {
                        throw new RuntimeException('An abandoned upload could not be removed; cleanup will retry.');
                    }

                    return 1;
                });
            }
        }

        $this->info($this->option('execute')
            ? "Removed {$count} abandoned upload files."
            : "Dry run: {$count} old generated uploads have no owning record. No files changed.");

        return self::SUCCESS;
    }

    private function isGeneratedPath(string $disk, string $path): bool
    {
        $parts = explode('/', $path);

        if (count($parts) !== 2 || ! Str::isUuid(pathinfo($parts[1], PATHINFO_FILENAME))) {
            return false;
        }

        $extension = pathinfo($parts[1], PATHINFO_EXTENSION);

        return $disk === 'local'
            ? $parts[0] === 'supplier-invoices' && $extension === 'pdf'
            : Str::isUuid($parts[0]) && in_array($extension, ['pdf', 'docx', 'txt', 'md', 'markdown'], true);
    }
}
