<?php

namespace App\Enums;

enum TicketDepartment: string
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
            self::Technical => 'Technical support',
            self::Account => 'Account services',
            self::Shipping => 'Shipping',
            self::Returns => 'Returns',
            self::General => 'General support',
        };
    }
}
