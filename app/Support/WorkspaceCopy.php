<?php

namespace App\Support;

final class WorkspaceCopy
{
    public const string DISCLOSURE = 'Your documents and questions are sent to OpenAI so this preview can index them and answer. This organization does not share that content for model training, model feedback, or evaluation and fine-tuning. OpenAI may still keep abuse-monitoring logs for up to 30 days. Deleting this workspace removes the files here. It does not erase those logs.';

    public const string CAPACITY = 'This server cannot store more documents right now. Try again later, or delete a document from this workspace.';

    public const string LEAVE = 'Forget this browser. The workspace and your files stay until the expiration date.';

    public const string DELETE = 'Delete this workspace now. Files, chats, and the return code stop working.';

    public const string GAP = 'I couldn’t find an answer in these documents. If you have a document that covers it, you can upload it and ask again.';

    public const string WRONG_CODE = 'That return code does not open a workspace.';

    public const string EXPIRED = 'This workspace has expired.';

    public const string PASSWORD = 'This file is password-protected.';

    public const string NO_TEXT = 'No readable text was found. This file may be blank or contain only images. Upload a copy with text you can highlight and copy; image-only scans cannot be read here.';

    public const string BLANK = 'This file is blank or contains only whitespace. Add text and upload it again.';

    public static function fileFailureMessage(?string $reason): string
    {
        return match ($reason) {
            self::BLANK => 'This file is blank. Upload a copy containing text.',
            self::NO_TEXT, 'This file has no extractable text.' => 'No readable text found. Upload a text-readable copy.',
            self::PASSWORD => 'Password-protected file. Upload an unlocked copy.',
            null, '' => 'Could not read this file. Upload another copy.',
            default => $reason,
        };
    }

    public static function withoutAnswerSignOff(string $body): string
    {
        return preg_replace('/(?:^|(?<=[.!?])\s+|\R+)Thank you[.!]?\s*\z/i', '', $body) ?? $body;
    }
}
