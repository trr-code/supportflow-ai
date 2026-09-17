<?php

namespace App\Support;

class ChatAnswerHtml
{
    /**
     * Escape model text, then apply a closed subset of emphasis, lists, and paragraphs.
     * Output is safe for Livewire stream innerHTML replace mode.
     */
    public static function render(string $text): string
    {
        $text = ChatCitationTrailer::visible($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        if (substr_count($text, '**') % 2 === 1) {
            $text = self::replace('/\*\*(?!.*\*\*)/s', '', $text);
        }

        $text = self::replace('/[ \t]+-\s+(?=(?:\*\*)?[A-Za-z][A-Za-z0-9 \/&-]{0,39}:)/', "\n- ", $text);
        $text = self::replace('/(?<=\S)[ \t]+(?=(?:\*\*)?[A-Z][a-z][A-Za-z0-9 \/&-]{0,37}:\s+\S)/', "\n", $text);

        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = self::replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped);

        $blocks = preg_split("/\n{2,}/", $escaped) ?: [];
        $html = [];

        foreach ($blocks as $block) {
            $rendered = self::renderBlock($block);

            if ($rendered !== '') {
                $html[] = $rendered;
            }
        }

        return implode('', $html);
    }

    protected static function renderBlock(string $block): string
    {
        $lines = explode("\n", $block);
        $html = [];
        $paragraph = [];
        $items = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || preg_match('/^[\-\*]$/', $trimmed) === 1) {
                continue;
            }

            if (preg_match('/^[\-\*]\s+(.+)$/', $trimmed, $match) === 1) {
                self::appendParagraph($html, $paragraph);
                $items[] = '<li>'.self::emphasizeLabel($match[1]).'</li>';

                continue;
            }

            if (self::startsWithLabel($trimmed)) {
                self::appendParagraph($html, $paragraph);
                $items[] = '<li>'.self::emphasizeLabel($trimmed).'</li>';

                continue;
            }

            self::appendList($html, $items);
            $paragraph[] = self::emphasizeLabel($trimmed);
        }

        self::appendParagraph($html, $paragraph);
        self::appendList($html, $items);

        return implode('', $html);
    }

    protected static function startsWithLabel(string $text): bool
    {
        if (str_starts_with($text, '<strong>')) {
            $text = (string) preg_replace('/^<strong>(.*?)<\/strong>/s', '$1', $text);
        }

        return preg_match('/^[A-Za-z][A-Za-z0-9][A-Za-z0-9 \/&-]{0,38}:\s+\S/', $text) === 1;
    }

    protected static function emphasizeLabel(string $text): string
    {
        if (str_starts_with($text, '<strong>')) {
            return $text;
        }

        return self::replace(
            '/^([A-Za-z][A-Za-z0-9][A-Za-z0-9 \/&-]{0,38}:)(\s+\S.*)$/',
            '<strong>$1</strong>$2',
            $text,
        );
    }

    /**
     * @param  list<string>  $html
     * @param  list<string>  $paragraph
     */
    protected static function appendParagraph(array &$html, array &$paragraph): void
    {
        if ($paragraph === []) {
            return;
        }

        $html[] = '<p>'.implode('<br>', $paragraph).'</p>';
        $paragraph = [];
    }

    /**
     * @param  list<string>  $html
     * @param  list<string>  $items
     */
    protected static function appendList(array &$html, array &$items): void
    {
        if ($items === []) {
            return;
        }

        $html[] = '<ul>'.implode('', $items).'</ul>';
        $items = [];
    }

    protected static function replace(string $pattern, string $replacement, string $subject): string
    {
        return preg_replace($pattern, $replacement, $subject) ?? $subject;
    }
}
