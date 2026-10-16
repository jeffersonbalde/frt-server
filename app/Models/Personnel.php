<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Personnel extends Model
{
    protected $table = 'personnel';

    protected $fillable = ['name', 'position', 'is_active'];

    public const POSITIONS = [
        'Driver / Equipment Operator',
        'Operations / Milling Personnel',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
