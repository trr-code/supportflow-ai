<?php

namespace App\Enums;

enum SupplierInvoiceStatus: string
{
    case Processing = 'processing';
    case Failed = 'failed';
    case NeedsReview = 'needs_review';
    case Confirmed = 'confirmed';
}
