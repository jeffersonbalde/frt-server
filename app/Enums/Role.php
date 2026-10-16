<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Operator = 'operator';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner / Manager',
            self::Operator => 'Operator / Recorder',
        };
    }
}
