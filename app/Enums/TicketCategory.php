<?php

namespace App\Enums;

enum TicketCategory: string
{
    case Billing = 'billing';
    case Technical = 'technical';
    case Account = 'account';
    case Shipping = 'shipping';
    case Returns = 'returns';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Billing => 'Billing',
            self::Technical => 'Technical',
            self::Account => 'Account',
            self::Shipping => 'Shipping',
            self::Returns => 'Returns',
            self::General => 'General',
        };
    }
}
