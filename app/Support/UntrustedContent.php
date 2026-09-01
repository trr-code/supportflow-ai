<?php

namespace App\Support;

class UntrustedContent
{
    public static function wrap(string $source, string $content): string
    {
        $safeSource = str_replace(['<', '>'], '', $source);

        return <<<TXT
<untrusted_content source="{$safeSource}">
{$content}
</untrusted_content>
TXT;
    }
}
