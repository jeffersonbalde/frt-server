<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    /**
     * Defaults from the FRT System PPT (Start of Day) and the capstone (Ch. 4 deductions).
     * Rates and prices are in centavos.
     *
     * @var array<string, string|int|float>
     */
    public const DEFAULTS = [
        'business_name' => 'Fabriga Rice Trading',
        'business_address' => 'P-2 Sta. Cruz, Mahayag, Z.D.S.',
        'business_contact' => '09387252257',
        'messenger' => 'Daryl Fabriga',
        'rice_rate' => 400,
        'tahop_buy_rate' => 1500,
        'tahop_sell_rate' => 2000,
        'fuel_price' => 9500,
        'labor_pct' => 30,
        'fuel_pct' => 10,
    ];

    public const MONEY_KEYS = ['rice_rate', 'tahop_buy_rate', 'tahop_sell_rate', 'fuel_price'];

    public const PERCENT_KEYS = ['labor_pct', 'fuel_pct'];

    /**
     * All settings merged over the defaults, with numeric values cast.
     *
     * @return array<string, string|int|float>
     */
    public static function values(): array
    {
        $stored = static::query()->pluck('value', 'key')->all();
        $values = array_merge(static::DEFAULTS, array_intersect_key($stored, static::DEFAULTS));

        foreach (static::MONEY_KEYS as $key) {
            $values[$key] = (int) $values[$key];
        }

        foreach (static::PERCENT_KEYS as $key) {
            $values[$key] = (float) $values[$key];
        }

        return $values;
    }

    /** Keys of the owner's void approval code (hashed), kept apart from the price settings. */
    private const VOID_CODE_KEYS = ['void_code_hash', 'void_code_salt', 'void_code_set_at'];

    /**
     * The owner's void approval code as phones check it: sha256("salt:code"), the same scheme as the phone PIN.
     *
     * @return array{hash: string, salt: string, set_at: string}|null
     */
    public static function voidCode(): ?array
    {
        $stored = static::query()->whereIn('key', self::VOID_CODE_KEYS)->pluck('value', 'key');

        if (! $stored->has('void_code_hash') || ! $stored->has('void_code_salt')) {
            return null;
        }

        return [
            'hash' => (string) $stored['void_code_hash'],
            'salt' => (string) $stored['void_code_salt'],
            'set_at' => (string) $stored->get('void_code_set_at', ''),
        ];
    }

    public static function setVoidCode(string $code): void
    {
        $salt = bin2hex(random_bytes(16));

        foreach ([
            'void_code_hash' => hash('sha256', "{$salt}:{$code}"),
            'void_code_salt' => $salt,
            'void_code_set_at' => now()->toIso8601String(),
        ] as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    /**
     * A random ID for this copy of the business data. Phones remember it; when it changes (a fresh
     * database, `frt:reset-data`, or a different server) they replace their customer list with the
     * server's instead of keeping old copies next to new ones.
     */
    public static function databaseId(): string
    {
        return (string) static::query()->firstOrCreate(['key' => 'database_id'], ['value' => (string) Str::uuid()])->value;
    }

    /** Give the business data a new ID, so every phone refreshes its customer list on the next sync. */
    public static function renewDatabaseId(): void
    {
        static::query()->updateOrCreate(['key' => 'database_id'], ['value' => (string) Str::uuid()]);
    }

    /**
     * @param  array<string, string|int|float|null>  $values
     */
    public static function store(array $values): void
    {
        foreach (array_intersect_key($values, static::DEFAULTS) as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }
    }
}
