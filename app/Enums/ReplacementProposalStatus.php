<?php

namespace App\Enums;

enum ReplacementProposalStatus: string
{
    case Pending = 'pending';
    case Confirming = 'confirming';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Superseded = 'superseded';
    case Expired = 'expired';
}
