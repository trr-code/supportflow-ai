<?php

namespace App\Support;

class KnowledgeChunker
{
    /**
     * Split markdown-ish article body into retrieval chunks.
     *
     * @return list<array{heading: ?string, body: string}>
     */
    public static function chunk(string $body): array
    {
        $sections = preg_split('/^##\s+/m', trim($body)) ?: [];
        $chunks = [];

        foreach ($sections as $index => $section) {
            $section = trim($section);

            if ($section === '') {
                continue;
            }

            $heading = null;
            $text = $section;

            if ($index > 0) {
                $lines = preg_split("/\r\n|\n|\r/", $section) ?: [$section];
                $heading = trim((string) array_shift($lines));
                $text = trim(implode("\n", $lines));
            }

            if ($text === '') {
                $text = $heading ?? $section;
            }

            foreach (self::splitLong($text) as $part) {
                $chunks[] = [
                    'heading' => $heading,
                    'body' => $part,
                ];
            }
        }

        return $chunks === [] ? [['heading' => null, 'body' => trim($body)]] : $chunks;
    }

    /**
     * @return list<string>
     */
    protected static function splitLong(string $text, int $max = 700): array
    {
        if (strlen($text) <= $max) {
            return [$text];
        }

        $paragraphs = preg_split("/\n{2,}/", $text) ?: [$text];
        $parts = [];
        $buffer = '';

        foreach ($paragraphs as $paragraph) {
            $candidate = $buffer === '' ? $paragraph : $buffer."\n\n".$paragraph;

            if (strlen($candidate) > $max && $buffer !== '') {
                $parts[] = $buffer;
                $buffer = $paragraph;

                continue;
            }

            $buffer = $candidate;
        }

        if ($buffer !== '') {
            $parts[] = $buffer;
        }

        return $parts;
    }
}
