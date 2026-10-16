<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DaySession;
use App\Models\Device;
use App\Models\Personnel;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Receives offline records from a phone and returns the reference data the phone needs.
 *
 * Every row is keyed by a UUID generated on the phone, so pushing the same batch twice is harmless.
 * When a row already exists, the copy with the newer client `updated_at` wins.
 */
class SyncService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{accepted: array<string, list<string>>, rejected: list<array{table: string, uuid: string, reason: string}>}
     */
    public function push(Device $device, User $user, array $payload): array
    {
        $accepted = ['customers' => [], 'day_sessions' => [], 'transactions' => []];
        $rejected = [];

        DB::transaction(function () use ($device, $payload, &$accepted, &$rejected) {
            $validUserIds = User::query()->pluck('id')->flip();

            foreach ($payload['customers'] as $row) {
                $this->upsertCustomer($device, $row);
                $accepted['customers'][] = $row['uuid'];
            }

            foreach ($payload['day_sessions'] as $row) {
                $existing = DaySession::query()->where('uuid', $row['uuid'])->first();

                if ($existing && (int) $existing->device_id !== $device->id) {
                    $rejected[] = ['table' => 'day_sessions', 'uuid' => $row['uuid'], 'reason' => 'Belongs to another device.'];

                    continue;
                }

                $this->upsertDaySession(
                    $device,
                    $existing,
                    $row,
                    $validUserIds->has($row['recorded_by'] ?? 0),
                    $validUserIds->has($row['reopened_by'] ?? 0),
                );
                $accepted['day_sessions'][] = $row['uuid'];
            }

            foreach ($payload['transactions'] as $row) {
                $session = DaySession::query()->where('uuid', $row['day_session_uuid'])->first();

                if (! $session || (int) $session->device_id !== $device->id) {
                    $rejected[] = ['table' => 'transactions', 'uuid' => $row['uuid'], 'reason' => 'Day session not found for this device.'];

                    continue;
                }

                $existing = Transaction::query()->where('uuid', $row['uuid'])->first();

                if ($existing && (int) $existing->device_id !== $device->id) {
                    $rejected[] = ['table' => 'transactions', 'uuid' => $row['uuid'], 'reason' => 'Belongs to another device.'];

                    continue;
                }

                $receiptTaken = Transaction::query()
                    ->where('receipt_no', $row['receipt_no'])
                    ->where('uuid', '!=', $row['uuid'])
                    ->exists();

                if ($receiptTaken) {
                    $rejected[] = ['table' => 'transactions', 'uuid' => $row['uuid'], 'reason' => 'Receipt number already used.'];

                    continue;
                }

                $this->upsertTransaction(
                    $device,
                    $session,
                    $existing,
                    $row,
                    $validUserIds->has($row['recorded_by'] ?? 0),
                    $validUserIds->has($row['voided_by'] ?? 0),
                );
                $accepted['transactions'][] = $row['uuid'];
            }

            $device->update(['last_sync_at' => now()]);
        });

        $device->update(['last_user_id' => $user->id]);

        return ['accepted' => $accepted, 'rejected' => $rejected];
    }

    /**
     * Settings, active personnel, and customers changed since the given time.
     *
     * The phone also sends the database ID it last saw. If there is none, or it no longer matches (the
     * server data was reset or the phone moved to another server), every customer is sent with
     * `customers_full`, so the phone can drop its old copies instead of showing each customer twice.
     *
     * @return array<string, mixed>
     */
    public function referenceData(?string $customersSince, ?string $databaseId = null): array
    {
        $serverTime = now();
        $currentDatabaseId = Setting::databaseId();
        $full = $customersSince === null || $databaseId !== $currentDatabaseId;

        $customers = Customer::query()
            ->when(! $full, fn ($query) => $query->where('updated_at', '>', $this->toAppTime($customersSince)))
            ->orderBy('id')
            ->get()
            ->map(fn (Customer $customer) => $customer->toSyncArray())
            ->all();

        return [
            'settings' => Setting::values(),
            // Hash of the owner's void approval code, so phones can approve voids even with no signal.
            'void_code' => Setting::voidCode(),
            'personnel' => Personnel::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'position'])
                ->toArray(),
            'customers' => $customers,
            'customers_full' => $full,
            'database_id' => $currentDatabaseId,
            'server_time' => $serverTime->toIso8601ZuluString('millisecond'),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsertCustomer(Device $device, array $row): void
    {
        $existing = Customer::query()->where('uuid', $row['uuid'])->first();
        $updatedAt = $this->toAppTime($row['updated_at']);

        if ($existing && $existing->client_updated_at->gte($updatedAt)) {
            return;
        }

        $attributes = [
            ...Arr::only($row, ['name', 'address', 'contact_no', 'facebook', 'age']),
            'client_updated_at' => $updatedAt,
        ];

        if ($existing) {
            $existing->update($attributes);

            return;
        }

        Customer::query()->create([...$attributes, 'uuid' => $row['uuid'], 'device_id' => $device->id]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsertDaySession(Device $device, ?DaySession $existing, array $row, bool $recorderExists, bool $reopenerExists): void
    {
        $updatedAt = $this->toAppTime($row['updated_at']);

        if ($existing && $existing->client_updated_at->gte($updatedAt)) {
            return;
        }

        $attributes = [
            ...Arr::only($row, [
                'business_date', 'status', 'rice_rate', 'tahop_buy_rate', 'tahop_sell_rate', 'fuel_price',
                'opening_fund', 'labor_pct', 'fuel_pct', 'operators', 'cash_count', 'cash_counted', 'cash_on_hand',
                'tahop_in_kind', 'total_collected', 'total_sales', 'variance', 'remarks',
                'reopen_count', 'reopen_reason', 'reopen_previous_variance',
            ]),
            'recorded_by' => $recorderExists ? $row['recorded_by'] : null,
            'started_at' => $this->toAppTime($row['started_at']),
            'closed_at' => isset($row['closed_at']) ? $this->toAppTime($row['closed_at']) : null,
            'client_updated_at' => $updatedAt,
        ];

        // Phones on an older app version do not send reopen details; leave those columns as they are.
        if (array_key_exists('reopened_at', $row)) {
            $attributes['reopened_at'] = isset($row['reopened_at']) ? $this->toAppTime($row['reopened_at']) : null;
            $attributes['reopened_by'] = $reopenerExists ? $row['reopened_by'] : null;
        }

        if ($existing) {
            $existing->update($attributes);

            return;
        }

        DaySession::query()->create([...$attributes, 'uuid' => $row['uuid'], 'device_id' => $device->id]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsertTransaction(
        Device $device,
        DaySession $session,
        ?Transaction $existing,
        array $row,
        bool $recorderExists,
        bool $voiderExists,
    ): void {
        $updatedAt = $this->toAppTime($row['updated_at']);

        if ($existing && $existing->client_updated_at->gte($updatedAt)) {
            return;
        }

        $customerId = isset($row['customer_uuid'])
            ? Customer::query()->where('uuid', $row['customer_uuid'])->value('id')
            : null;

        $attributes = [
            ...Arr::only($row, [
                'receipt_no', 'type', 'customer_name', 'location', 'sacks', 'sack_count', 'total_kg', 'rate', 'gross',
                'tahop_kg', 'tahop_rate', 'tahop_offset', 'adjustment', 'amount_due', 'cash', 'change',
                'labor', 'fuel', 'net', 'status', 'void_reason',
            ]),
            'day_session_id' => $session->id,
            'business_date' => $session->business_date,
            'customer_id' => $customerId,
            'recorded_by' => $recorderExists ? $row['recorded_by'] : null,
            'voided_at' => isset($row['voided_at']) ? $this->toAppTime($row['voided_at']) : null,
            'voided_by' => $voiderExists ? $row['voided_by'] : null,
            'recorded_at' => $this->toAppTime($row['recorded_at']),
            'client_updated_at' => $updatedAt,
        ];

        if ($existing) {
            $existing->update($attributes);

            return;
        }

        Transaction::query()->create([...$attributes, 'uuid' => $row['uuid'], 'device_id' => $device->id]);
    }

    private function toAppTime(string $value): Carbon
    {
        return Carbon::parse($value)->setTimezone(config('app.timezone'));
    }
}
