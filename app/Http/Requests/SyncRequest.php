<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncRequest extends FormRequest
{
    public const MAX_ROWS = 500;

    /** ₱10,000 per kg, in centavos — the same limit as the app. */
    public const MAX_RATE = 1000000;

    /** ₱999,999,999.99 in centavos. */
    public const MAX_AMOUNT = 99999999999;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Plain-language names so a rejected sync reads "Sack weight" instead of "transactions.0.sacks.3".
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'transactions.*.sacks.*' => 'sack weight',
            'transactions.*.rate' => 'price per kg',
            'transactions.*.cash' => 'cash received',
            'day_sessions.*.rice_rate' => 'rice price per kg',
            'day_sessions.*.tahop_buy_rate' => 'tahop buying price',
            'day_sessions.*.tahop_sell_rate' => 'tahop selling price',
            'day_sessions.*.fuel_price' => 'oil/gas price',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $max = self::MAX_ROWS;

        return [
            'device_uuid' => ['required', 'uuid'],
            'customers_since' => ['nullable', 'date'],

            'customers' => ['present', 'array', "max:$max"],
            'customers.*.uuid' => ['required', 'uuid'],
            'customers.*.name' => ['required', 'string', 'max:255'],
            'customers.*.address' => ['nullable', 'string', 'max:255'],
            'customers.*.contact_no' => ['nullable', 'string', 'max:50'],
            'customers.*.facebook' => ['nullable', 'string', 'max:255'],
            'customers.*.age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'customers.*.updated_at' => ['required', 'date'],

            'day_sessions' => ['present', 'array', "max:$max"],
            'day_sessions.*.uuid' => ['required', 'uuid'],
            'day_sessions.*.business_date' => ['required', 'date_format:Y-m-d'],
            'day_sessions.*.status' => ['required', Rule::in(['open', 'closed'])],
            'day_sessions.*.recorded_by' => ['nullable', 'integer'],
            'day_sessions.*.rice_rate' => ['required', 'integer', 'min:0', 'max:'.self::MAX_RATE],
            'day_sessions.*.tahop_buy_rate' => ['required', 'integer', 'min:0', 'max:'.self::MAX_RATE],
            'day_sessions.*.tahop_sell_rate' => ['required', 'integer', 'min:0', 'max:'.self::MAX_RATE],
            'day_sessions.*.fuel_price' => ['required', 'integer', 'min:0', 'max:'.self::MAX_RATE],
            'day_sessions.*.opening_fund' => ['required', 'integer', 'min:0', 'max:'.self::MAX_AMOUNT],
            'day_sessions.*.labor_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'day_sessions.*.fuel_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'day_sessions.*.operators' => ['present', 'array'],
            'day_sessions.*.operators.*.id' => ['required', 'integer'],
            'day_sessions.*.operators.*.name' => ['required', 'string', 'max:255'],
            'day_sessions.*.cash_count' => ['nullable', 'array'],
            'day_sessions.*.cash_counted' => ['nullable', 'integer', 'between:-'.self::MAX_AMOUNT.','.self::MAX_AMOUNT],
            'day_sessions.*.cash_on_hand' => ['nullable', 'integer', 'between:-'.self::MAX_AMOUNT.','.self::MAX_AMOUNT],
            'day_sessions.*.tahop_in_kind' => ['nullable', 'integer', 'between:-'.self::MAX_AMOUNT.','.self::MAX_AMOUNT],
            'day_sessions.*.total_collected' => ['nullable', 'integer', 'between:-'.self::MAX_AMOUNT.','.self::MAX_AMOUNT],
            'day_sessions.*.total_sales' => ['nullable', 'integer', 'between:-'.self::MAX_AMOUNT.','.self::MAX_AMOUNT],
            'day_sessions.*.variance' => ['nullable', 'integer', 'between:-'.self::MAX_AMOUNT.','.self::MAX_AMOUNT],
            'day_sessions.*.remarks' => ['nullable', 'string', 'max:2000'],
            'day_sessions.*.started_at' => ['required', 'date'],
            'day_sessions.*.closed_at' => ['nullable', 'date'],
            'day_sessions.*.reopen_count' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'day_sessions.*.reopened_at' => ['nullable', 'date'],
            'day_sessions.*.reopened_by' => ['nullable', 'integer'],
            'day_sessions.*.reopen_reason' => ['nullable', 'string', 'max:255'],
            'day_sessions.*.reopen_previous_variance' => ['nullable', 'integer', 'between:-'.self::MAX_AMOUNT.','.self::MAX_AMOUNT],
            'day_sessions.*.updated_at' => ['required', 'date'],

            'transactions' => ['present', 'array', "max:$max"],
            'transactions.*.uuid' => ['required', 'uuid'],
            'transactions.*.day_session_uuid' => ['required', 'uuid'],
            'transactions.*.customer_uuid' => ['nullable', 'uuid'],
            'transactions.*.recorded_by' => ['nullable', 'integer'],
            'transactions.*.receipt_no' => ['required', 'string', 'max:32'],
            'transactions.*.type' => ['required', Rule::in([Transaction::TYPE_MILLING, Transaction::TYPE_TAHOP_SALE])],
            'transactions.*.customer_name' => ['nullable', 'string', 'max:255'],
            'transactions.*.location' => ['nullable', 'string', 'max:255'],
            'transactions.*.sacks' => ['nullable', 'array', 'max:500'],
            'transactions.*.sacks.*' => ['numeric', 'min:0', 'max:200'],
            'transactions.*.sack_count' => ['required', 'integer', 'min:0', 'max:500'],
            'transactions.*.total_kg' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'transactions.*.rate' => ['required', 'integer', 'min:0', 'max:'.self::MAX_RATE],
            'transactions.*.gross' => ['required', 'integer', 'between:-'.self::MAX_AMOUNT.','.self::MAX_AMOUNT],
            'transactions.*.tahop_kg' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'transactions.*.tahop_rate' => ['required', 'integer', 'min:0', 'max:'.self::MAX_RATE],
            'transactions.*.tahop_offset' => ['required', 'integer', 'between:-'.self::MAX_AMOUNT.','.self::MAX_AMOUNT],
            'transactions.*.adjustment' => ['required', 'integer', 'between:-'.self::MAX_AMOUNT.','.self::MAX_AMOUNT],
            'transactions.*.amount_due' => ['required', 'integer', 'between:-'.self::MAX_AMOUNT.','.self::MAX_AMOUNT],
            'transactions.*.cash' => ['required', 'integer', 'between:-'.self::MAX_AMOUNT.','.self::MAX_AMOUNT],
            'transactions.*.change' => ['required', 'integer', 'between:-'.self::MAX_AMOUNT.','.self::MAX_AMOUNT],
            'transactions.*.labor' => ['required', 'integer', 'between:-'.self::MAX_AMOUNT.','.self::MAX_AMOUNT],
            'transactions.*.fuel' => ['required', 'integer', 'between:-'.self::MAX_AMOUNT.','.self::MAX_AMOUNT],
            'transactions.*.net' => ['required', 'integer', 'between:-'.self::MAX_AMOUNT.','.self::MAX_AMOUNT],
            'transactions.*.status' => ['required', Rule::in(['valid', 'void'])],
            'transactions.*.void_reason' => ['nullable', 'string', 'max:255'],
            'transactions.*.voided_at' => ['nullable', 'date'],
            'transactions.*.voided_by' => ['nullable', 'integer'],
            'transactions.*.recorded_at' => ['required', 'date'],
            'transactions.*.updated_at' => ['required', 'date'],
        ];
    }
}
