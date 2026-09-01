<?php

namespace App\Enums;

enum UserRole: string
{
    case DemoAgent = 'demo_agent';
    case Admin = 'admin';
}
