<?php

namespace App\Console\Commands;

use App\Exceptions\DocumentExtractException;
use App\Support\DocumentParser;
use Illuminate\Console\Command;

class ExtractDocumentTextCommand extends Command
{
    #[\Override]
    protected $signature = 'documents:extract-text {path} {extension} {--max-characters=150000}';

    #[\Override]
    protected $description = 'Extract document text inside the resource-limited parser process';

    #[\Override]
    protected $hidden = true;

    public function handle(DocumentParser $parser): int
    {
        $path = $this->argument('path');
        $extension = $this->argument('extension');

        if (! is_string($path) || ! is_string($extension)) {
            return self::INVALID;
        }

        try {
            $payload = ['text' => $parser->extract(
                $path,
                $extension,
                max(1, (int) $this->option('max-characters')),
            )];
        } catch (DocumentExtractException $exception) {
            $payload = ['error' => $exception->getMessage()];
        }

        $this->output->write(json_encode($payload, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));

        return self::SUCCESS;
    }
}
