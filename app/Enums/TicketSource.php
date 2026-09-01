<?php

namespace App\Enums;

enum TicketSource: string
{
    case VisitorDemo = 'visitor_demo';
    case Seeded = 'seeded';
    case Scenario = 'scenario';
}
