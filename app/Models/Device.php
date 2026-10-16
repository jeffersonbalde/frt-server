<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $fillable = ['uuid', 'code', 'name', 'last_user_id', 'last_sync_at'];

    protected function casts(): array
    {
        return [
            'last_sync_at' => 'datetime',
        ];
    }

    /**
     * Find a device by its install UUID, or register it with the next free code (A, B, … Z, AA, AB, …).
     */
    public static function register(string $uuid, ?string $name): self
    {
        $device = static::query()->where('uuid', $uuid)->first();

        if ($device) {
            if ($name && $device->name !== $name) {
                $device->update(['name' => $name]);
            }

            return $device;
        }

        return static::query()->create([
            'uuid' => $uuid,
            'code' => static::codeFor(static::query()->count()),
            'name' => $name,
        ]);
    }

    public static function codeFor(int $index): string
    {
        $code = '';
        $index++;

        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $code = chr(65 + $remainder).$code;
            $index = intdiv($index - 1, 26);
        }

        return $code;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lastUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_user_id');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
