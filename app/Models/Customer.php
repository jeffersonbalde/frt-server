<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'uuid', 'device_id', 'name', 'address', 'contact_no', 'facebook', 'age', 'client_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'age' => 'integer',
            'client_updated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSyncArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'address' => $this->address,
            'contact_no' => $this->contact_no,
            'facebook' => $this->facebook,
            'age' => $this->age,
            'updated_at' => $this->client_updated_at->toIso8601ZuluString('millisecond'),
        ];
    }
}
