<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DaySession extends Model
{
    protected $fillable = [
        'uuid', 'device_id', 'recorded_by', 'business_date', 'status',
        'rice_rate', 'tahop_buy_rate', 'tahop_sell_rate', 'fuel_price', 'opening_fund', 'labor_pct', 'fuel_pct',
        'operators', 'cash_count', 'cash_counted', 'cash_on_hand', 'tahop_in_kind', 'total_collected',
        'total_sales', 'variance', 'remarks', 'started_at', 'closed_at', 'client_updated_at',
        'reopen_count', 'reopened_at', 'reopened_by', 'reopen_reason', 'reopen_previous_variance',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'operators' => 'array',
            'cash_count' => 'array',
            'labor_pct' => 'float',
            'fuel_pct' => 'float',
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
            'client_updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Who reopened the day after End of Day (with the owner's approval code).
     *
     * @return BelongsTo<User, $this>
     */
    public function reopener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return list<string>
     */
    public function operatorNames(): array
    {
        return array_values(array_map(fn (array $operator) => (string) $operator['name'], $this->operators ?? []));
    }
}
