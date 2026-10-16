<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    public const TYPE_MILLING = 'milling';

    public const TYPE_TAHOP_SALE = 'tahop_sale';

    protected $fillable = [
        'uuid', 'device_id', 'day_session_id', 'customer_id', 'recorded_by', 'receipt_no', 'business_date', 'type',
        'customer_name', 'location', 'sacks', 'sack_count', 'total_kg', 'rate', 'gross',
        'tahop_kg', 'tahop_rate', 'tahop_offset', 'adjustment', 'amount_due', 'cash', 'change',
        'labor', 'fuel', 'net', 'status', 'void_reason', 'voided_at', 'voided_by', 'recorded_at', 'client_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'sacks' => 'array',
            'total_kg' => 'float',
            'tahop_kg' => 'float',
            'voided_at' => 'datetime',
            'recorded_at' => 'datetime',
            'client_updated_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Transaction>  $query
     */
    public function scopeValid(Builder $query): void
    {
        $query->where('status', 'valid');
    }

    public function typeLabel(): string
    {
        return $this->type === self::TYPE_MILLING ? 'Rice Milling' : 'Tahop Sale';
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<DaySession, $this>
     */
    public function daySession(): BelongsTo
    {
        return $this->belongsTo(DaySession::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * The user who voided this transaction (with the owner's void approval code).
     *
     * @return BelongsTo<User, $this>
     */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
