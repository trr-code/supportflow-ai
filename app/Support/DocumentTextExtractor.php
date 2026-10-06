<?php

namespace App\Support;

use App\Exceptions\DocumentExtractException;
use Smalot\PdfParser\Parser;
use ZipArchive;

class DocumentTextExtractor
{
    public function extract(string $absolutePath, string $extension): string
    {
        $started = hrtime(true);
        $beforePeak = memory_get_peak_usage(true);
        $extension = strtolower($extension);

        try {
            $text = match ($extension) {
                'txt', 'md', 'markdown' => $this->plain($absolutePath),
                'docx' => $this->docx($absolutePath),
                'pdf' => $this->pdf($absolutePath),
                default => throw new DocumentExtractException('This file type is not supported.'),
            };
        } catch (DocumentExtractException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new DocumentExtractException('This document could not be read.', previous: $exception);
        }

        $this->guardLimits($started, $beforePeak);

        $text = trim($text);

        if ($text === '') {
            throw new DocumentExtractException(WorkspaceCopy::NO_TEXT);
        }

        $max = (int) config('supportflow.workspaces.max_extracted_characters', 150_000);

        if (mb_strlen($text) > $max) {
            throw new DocumentExtractException('This document is longer than this preview can index.');
        }

        return $text;
    }

    private function plain(string $absolutePath): string
    {
        $text = file_get_contents($absolutePath);

        if ($text === false) {
            throw new DocumentExtractException('This document could not be read.');
        }

        return $text;
    }

    private function docx(string $absolutePath): string
    {
        $zip = new ZipArchive;

        if ($zip->open($absolutePath) !== true) {
            throw new DocumentExtractException('This document could not be read.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($xml) || $xml === '') {
            throw new DocumentExtractException(WorkspaceCopy::NO_TEXT);
        }

        if (strlen($xml) > 2_000_000) {
            throw new DocumentExtractException('This document is longer than this preview can index.');
        }

        $withBreaks = str_replace(['</w:p>', '</w:tr>'], "\n", $xml);
        $text = strip_tags($withBreaks);

        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function pdf(string $absolutePath): string
    {
        try {
            $pdf = (new Parser)->parseFile($absolutePath);

            return $pdf->getText();
        } catch (DocumentExtractException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();

            if (str_contains($message, 'Secured pdf') || str_contains(strtolower($message), 'secured file')) {
                throw new DocumentExtractException(WorkspaceCopy::PASSWORD, previous: $exception);
            }

            throw new DocumentExtractException('This document could not be read.', previous: $exception);
        }
    }

    private function guardLimits(int $started, int $beforePeak): void
    {
        $seconds = (hrtime(true) - $started) / 1_000_000_000;
        $limitSeconds = (int) config('supportflow.workspaces.extract_seconds', 30);
        $growth = memory_get_peak_usage(true) - $beforePeak;
        $limitBytes = (int) config('supportflow.workspaces.extract_memory_bytes', 256 * 1024 * 1024);

        if ($seconds > $limitSeconds || $growth > $limitBytes) {
            throw new DocumentExtractException('This document could not be read.');
        }
    }
}
