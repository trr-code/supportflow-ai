<?php

namespace App\Support;

final class WorkspaceCopy
{
    public const string DISCLOSURE = 'Your documents and questions are sent to OpenAI so this preview can index them and answer. This organization does not share that content for model training, model feedback, or evaluation and fine-tuning. OpenAI may still keep abuse-monitoring logs for up to 30 days. Deleting this workspace removes the files here. It does not erase those logs.';

    public const string CAPACITY = 'This server cannot store more documents right now. Try again later, or delete a document from this workspace.';

    public const string FAILURE = 'This document could not be read. It is not available for answers. You can delete it and upload another file.';

    public const string LEAVE = 'Forget this browser. The workspace and your files stay until the expiration date.';

    public const string DELETE = 'Delete this workspace now. Files, chats, and the return code stop working.';

    public const string GAP = 'These documents do not contain an answer to that.';

    public const string WRONG_CODE = 'That return code does not open a workspace.';

    public const string EXPIRED = 'This workspace has expired.';

    public const string PASSWORD = 'This file is password-protected.';

    public const string NO_TEXT = 'This file has no extractable text.';
}
