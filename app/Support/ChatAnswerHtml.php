<?php

namespace App\Support;

class ChatAnswerHtml
{
    public static function revealedText(string $body, int $characters): string
    {
        $parts = preg_split('/(<[^>]+>)/', self::render($body), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $text = '';
        foreach ($parts as $part) {
            if ($characters <= 0) {
                break;
            }
            if (str_starts_with($part, '<')) {
                if (preg_match('/^<\/(?:p|li)>|^<br\b/', $part)) {
                    $text .= "\n";
                }

                continue;
            }
            $decoded = html_entity_decode($part, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $prefix = mb_substr($decoded, 0, $characters);
            $text .= $prefix;
            $characters -= mb_strlen($prefix);
        }

        return trim($text);
    }

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
        $text = self::splitBeforeLabels($text);

        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = self::replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped);
        $escaped = self::replace('/(?<![\pL\pN*])\*(?![\s*])([^*\r\n]+?)(?<!\s)\*(?![\pL\pN*])/u', '<em>$1</em>', $escaped);

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
        $ordered = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || preg_match('/^[\-\*]$/', $trimmed) === 1) {
                continue;
            }

            if (preg_match('/^(?:[\-\*]|\d+[.)])\s+(.+)$/', $trimmed, $match) === 1) {
                self::appendParagraph($html, $paragraph);
                $isOrdered = preg_match('/^\d/', $trimmed) === 1;
                if ($items !== [] && $isOrdered !== $ordered) {
                    self::appendList($html, $items, $ordered);
                }
                $ordered = $isOrdered;
                $items[] = '<li>'.self::emphasizeLabel($match[1]).'</li>';

                continue;
            }

            if (self::startsWithLabel($trimmed)) {
                self::appendParagraph($html, $paragraph);
                $items[] = '<li>'.self::emphasizeLabel($trimmed).'</li>';

                continue;
            }

            self::appendList($html, $items, $ordered);
            $paragraph[] = self::emphasizeLabel($trimmed);
        }

        self::appendParagraph($html, $paragraph);
        self::appendList($html, $items, $ordered);

        return implode('', $html);
    }

    /**
     * Start a new line before a label, but keep a multi-word name such as "Field Kit:" intact.
     */
    protected static function splitBeforeLabels(string $text): string
    {
        $pattern = '/(?<=\S)[ \t]+(?=(?:\*\*)?[A-Z][a-z][A-Za-z0-9 \/&-]{0,37}:\s+\S)/';

        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return $text;
        }

        $result = '';
        $offset = 0;

        foreach ($matches[0] as [$whitespace, $position]) {
            $prefix = substr($text, 0, $position);
            $result .= substr($text, $offset, $position - $offset);
            $result .= preg_match('/[A-Z][A-Za-z]{1,40}$/', $prefix) === 1 ? $whitespace : "\n";
            $offset = $position + strlen($whitespace);
        }

        return $result.substr($text, $offset);
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
    protected static function appendList(array &$html, array &$items, bool $ordered = false): void
    {
        if ($items === []) {
            return;
        }

        $tag = $ordered ? 'ol' : 'ul';
        $html[] = '<'.$tag.'>'.implode('', $items).'</'.$tag.'>';
        $items = [];
    }

    protected static function replace(string $pattern, string $replacement, string $subject): string
    {
        return preg_replace($pattern, $replacement, $subject) ?? $subject;
    }
}
