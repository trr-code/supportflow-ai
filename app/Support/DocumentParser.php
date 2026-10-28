<?php

namespace App\Support;

use App\Exceptions\DocumentExtractException;
use Smalot\PdfParser\Parser;
use ZipArchive;

class DocumentParser
{
    public function extract(string $absolutePath, string $extension, int $maxCharacters = 150_000): string
    {

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

        $text = trim($text);

        if ($text === '') {
            throw new DocumentExtractException(WorkspaceCopy::NO_TEXT);
        }

        $max = $maxCharacters;

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

        try {
            $metadata = $zip->statName('word/document.xml');

            if ($metadata === false) {
                throw new DocumentExtractException(WorkspaceCopy::NO_TEXT);
            }

            if ($metadata['size'] > 2_000_000) {
                throw new DocumentExtractException('This document is longer than this preview can index.');
            }

            $xml = $zip->getFromName('word/document.xml', 2_000_001);
        } finally {
            $zip->close();
        }

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
}
