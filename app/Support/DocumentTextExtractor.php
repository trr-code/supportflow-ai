<?php

namespace App\Support;

use App\Exceptions\DocumentExtractException;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;

class DocumentTextExtractor
{
    public function extract(string $absolutePath, string $extension): string
    {
        $maxCharacters = (int) config('supportflow.workspaces.max_extracted_characters', 150_000);
        $memoryBytes = (int) config('supportflow.workspaces.extract_memory_bytes', 256 * 1024 * 1024);

        try {
            $result = Process::path(base_path())
                ->timeout((int) config('supportflow.workspaces.extract_seconds', 30))
                ->run([
                    (string) config('supportflow.workspaces.extract_php_binary'),
                    '-d', 'memory_limit='.max(1, $memoryBytes),
                    '-d', 'display_errors=0',
                    '-d', 'log_errors=0',
                    base_path('artisan'), 'documents:extract-text',
                    $absolutePath, strtolower($extension),
                    '--max-characters='.(string) $maxCharacters,
                    '--no-interaction', '--no-ansi',
                ]);
        } catch (ProcessTimedOutException) {
            throw new DocumentExtractException('This document could not be read within the extraction time limit.');
        }

        $payload = json_decode($result->output(), true);

        if (! $result->successful() || ! is_array($payload)) {
            throw new DocumentExtractException('This document could not be read.');
        }

        if (is_string($payload['error'] ?? null)) {
            throw new DocumentExtractException($payload['error']);
        }

        $text = $payload['text'] ?? null;

        if (! is_string($text) || trim($text) === '' || mb_strlen($text) > $maxCharacters) {
            throw new DocumentExtractException('This document could not be read.');
        }

        return $text;
    }
}
