<?php

namespace App\Enums;

enum MessageAuthorType: string
{
    case Customer = 'customer';
    case Agent = 'agent';
    case System = 'system';
}
