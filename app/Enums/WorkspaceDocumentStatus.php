<?php

namespace App\Enums;

enum WorkspaceDocumentStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
