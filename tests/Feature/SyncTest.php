<?php

use App\Http\Middleware\EnsureSessionNotExpired;
use App\Models\Customer;
use App\Models\DaySession;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed();
    $this->operator = User::factory()->create(['username' => 'daryl', 'password' => 'secret123']);
    $this->deviceUuid = (string) Str::uuid();

    $login = $this->postJson('/api/login', [
        'username' => 'daryl', 'password' => 'secret123', 'device_uuid' => $this->deviceUuid,
    ]);

    $this->token = $login->json('token');
});

/**
 * The FRT System PPT sample day: 10 sacks (621 kg) at ₱4 less 48 kg tahop at ₱15 = ₱1,764, then a tahop sale of 48 kg at ₱20.
 *
 * @return array<string, mixed>
 */
function pptSampleBatch(string $deviceUuid, int $userId): array
{
    $sessionUuid = (string) Str::uuid();
    $customerUuid = (string) Str::uuid();
    $now = now()->toIso8601ZuluString('millisecond');

    return [
        'device_uuid' => $deviceUuid,
        'customers_since' => null,
        'customers' => [[
            'uuid' => $customerUuid, 'name' => 'Layla Gay', 'address' => 'Salug ZDS', 'contact_no' => '09487632531',
            'facebook' => 'Layla Gay', 'age' => 46, 'updated_at' => $now,
        ]],
        'day_sessions' => [[
            'uuid' => $sessionUuid, 'business_date' => today()->toDateString(), 'status' => 'closed', 'recorded_by' => $userId,
            'rice_rate' => 400, 'tahop_buy_rate' => 1500, 'tahop_sell_rate' => 2000, 'fuel_price' => 9500,
            'opening_fund' => 0, 'labor_pct' => 30, 'fuel_pct' => 10,
            'operators' => [['id' => 1, 'name' => 'Danilo C. Fabriga'], ['id' => 3, 'name' => 'Joel Blagot'], ['id' => 4, 'name' => 'Kcire John Flores']],
            'cash_count' => ['100000' => 2, '50000' => 1, '20000' => 1, '2000' => 1, '100' => 4],
            'cash_counted' => 272400, 'cash_on_hand' => 272400, 'tahop_in_kind' => 72000,
            'total_collected' => 272400, 'total_sales' => 200400, 'variance' => 0, 'remarks' => null,
            'started_at' => $now, 'closed_at' => $now, 'updated_at' => $now,
        ]],
        'transactions' => [
            [
                'uuid' => (string) Str::uuid(), 'day_session_uuid' => $sessionUuid, 'customer_uuid' => $customerUuid,
                'recorded_by' => $userId, 'receipt_no' => 'A-000001', 'type' => 'milling', 'customer_name' => 'Layla Gay',
                'location' => 'Salug', 'sacks' => [64, 65, 60, 59, 61, 60, 66, 62, 64, 60], 'sack_count' => 10,
                'total_kg' => 621, 'rate' => 400, 'gross' => 248400, 'tahop_kg' => 48, 'tahop_rate' => 1500,
                'tahop_offset' => 72000, 'adjustment' => 0, 'amount_due' => 176400, 'cash' => 200000, 'change' => 23600,
                'labor' => 47628, 'fuel' => 17640, 'net' => 111132, 'status' => 'valid', 'void_reason' => null,
                'voided_at' => null, 'recorded_at' => $now, 'updated_at' => $now,
            ],
            [
                'uuid' => (string) Str::uuid(), 'day_session_uuid' => $sessionUuid, 'customer_uuid' => null,
                'recorded_by' => $userId, 'receipt_no' => 'A-000002', 'type' => 'tahop_sale', 'customer_name' => null,
                'location' => null, 'sacks' => null, 'sack_count' => 0, 'total_kg' => 48, 'rate' => 2000, 'gross' => 96000,
                'tahop_kg' => 0, 'tahop_rate' => 1500, 'tahop_offset' => 0, 'adjustment' => 0, 'amount_due' => 96000,
                'cash' => 100000, 'change' => 4000, 'labor' => 6480, 'fuel' => 2400, 'net' => 15120, 'status' => 'valid',
                'void_reason' => null, 'voided_at' => null, 'recorded_at' => $now, 'updated_at' => $now,
            ],
        ],
    ];
}

it('stores a synced day and links the customer', function () {
    $batch = pptSampleBatch($this->deviceUuid, $this->operator->id);

    $this->withToken($this->token)->postJson('/api/sync', $batch)
        ->assertOk()
        ->assertJsonCount(1, 'accepted.customers')
        ->assertJsonCount(1, 'accepted.day_sessions')
        ->assertJsonCount(2, 'accepted.transactions')
        ->assertJsonCount(0, 'rejected');

    $milling = Transaction::query()->where('receipt_no', 'A-000001')->first();

    expect($milling->amount_due)->toBe(176400)
        ->and($milling->change)->toBe(23600)
        ->and($milling->customer->name)->toBe('Layla Gay')
        ->and($milling->business_date->toDateString())->toBe(today()->toDateString())
        ->and($milling->sacks)->toHaveCount(10)
        ->and(DaySession::query()->first()->variance)->toBe(0);
});

it('is safe to send the same batch twice', function () {
    $batch = pptSampleBatch($this->deviceUuid, $this->operator->id);

    $this->withToken($this->token)->postJson('/api/sync', $batch)->assertOk();
    $this->withToken($this->token)->postJson('/api/sync', $batch)->assertOk()->assertJsonCount(2, 'accepted.transactions');

    expect(Transaction::query()->count())->toBe(2)
        ->and(DaySession::query()->count())->toBe(1)
        ->and(Customer::query()->count())->toBe(1);
});

it('applies a later void and ignores an older copy', function () {
    $batch = pptSampleBatch($this->deviceUuid, $this->operator->id);
    $this->withToken($this->token)->postJson('/api/sync', $batch)->assertOk();

    $voided = $batch;
    $voided['transactions'][0]['status'] = 'void';
    $voided['transactions'][0]['void_reason'] = 'Wrong kg entered';
    $voided['transactions'][0]['voided_at'] = now()->addMinute()->toIso8601ZuluString('millisecond');
    $voided['transactions'][0]['updated_at'] = now()->addMinute()->toIso8601ZuluString('millisecond');
    $this->withToken($this->token)->postJson('/api/sync', $voided)->assertOk();

    expect(Transaction::query()->where('receipt_no', 'A-000001')->value('status'))->toBe('void');

    $this->withToken($this->token)->postJson('/api/sync', $batch)->assertOk();

    expect(Transaction::query()->where('receipt_no', 'A-000001')->value('status'))->toBe('void');
});

it('rejects transactions whose day is unknown', function () {
    $batch = pptSampleBatch($this->deviceUuid, $this->operator->id);
    $batch['day_sessions'] = [];

    $this->withToken($this->token)->postJson('/api/sync', $batch)
        ->assertOk()
        ->assertJsonCount(0, 'accepted.transactions')
        ->assertJsonCount(2, 'rejected');
});

it('does not let one phone overwrite another phone\'s day', function () {
    $batch = pptSampleBatch($this->deviceUuid, $this->operator->id);
    $this->withToken($this->token)->postJson('/api/sync', $batch)->assertOk();

    $otherDevice = (string) Str::uuid();
    $otherToken = $this->postJson('/api/login', [
        'username' => 'daryl', 'password' => 'secret123', 'device_uuid' => $otherDevice,
    ])->json('token');

    $batch['device_uuid'] = $otherDevice;
    $batch['day_sessions'][0]['updated_at'] = now()->addHour()->toIso8601ZuluString('millisecond');

    $this->withToken($otherToken)->postJson('/api/sync', $batch)
        ->assertOk()
        ->assertJsonCount(0, 'accepted.day_sessions')
        ->assertJsonCount(0, 'accepted.transactions');
});

it('shares customers between phones', function () {
    $batch = pptSampleBatch($this->deviceUuid, $this->operator->id);
    $this->withToken($this->token)->postJson('/api/sync', $batch)->assertOk();

    $otherDevice = (string) Str::uuid();
    $login = $this->postJson('/api/login', [
        'username' => 'daryl', 'password' => 'secret123', 'device_uuid' => $otherDevice,
    ]);

    $login->assertJsonPath('customers.0.name', 'Layla Gay')->assertJsonPath('customers_full', true);

    $this->withToken($login->json('token'))->postJson('/api/sync', [
        'device_uuid' => $otherDevice,
        'customers_since' => $login->json('server_time'),
        'database_id' => $login->json('database_id'),
        'customers' => [], 'day_sessions' => [], 'transactions' => [],
    ])->assertOk()->assertJsonCount(0, 'customers')->assertJsonPath('customers_full', false);
});

it('sends every customer again when the server data was reset, so phones do not show them twice', function () {
    $this->withToken($this->token)->postJson('/api/sync', pptSampleBatch($this->deviceUuid, $this->operator->id))->assertOk();
    $first = $this->withToken($this->token)->postJson('/api/sync', [
        'device_uuid' => $this->deviceUuid, 'customers_since' => null,
        'customers' => [], 'day_sessions' => [], 'transactions' => [],
    ])->assertOk();
    $databaseId = $first->json('database_id');
    expect($databaseId)->toBeString()->not->toBeEmpty();

    $since = ['device_uuid' => $this->deviceUuid, 'customers_since' => $first->json('server_time'),
        'customers' => [], 'day_sessions' => [], 'transactions' => []];

    // Same data on the server: only changes are sent.
    $this->withToken($this->token)->postJson('/api/sync', [...$since, 'database_id' => $databaseId])
        ->assertOk()->assertJsonCount(0, 'customers')->assertJsonPath('customers_full', false);

    // A phone that never saw an ID (older app) or saw another server's ID gets the whole list.
    $this->withToken($this->token)->postJson('/api/sync', [...$since, 'database_id' => 'some-other-server'])
        ->assertOk()->assertJsonCount(Customer::query()->count(), 'customers')->assertJsonPath('customers_full', true);

    // After frt:reset-data the ID changes.
    Setting::renewDatabaseId();
    expect(Setting::databaseId())->not->toBe($databaseId);
});

it('signs out a deactivated operator on the next sync', function () {
    $this->operator->update(['is_active' => false]);

    $this->withToken($this->token)->postJson('/api/sync', pptSampleBatch($this->deviceUuid, $this->operator->id))
        ->assertUnauthorized();

    expect($this->operator->tokens()->count())->toBe(0);
});

it('validates the batch', function () {
    $batch = pptSampleBatch($this->deviceUuid, $this->operator->id);
    $batch['transactions'][0]['type'] = 'unknown';

    $this->withToken($this->token)->postJson('/api/sync', $batch)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('transactions.0.type');
});

it('requires a token', function () {
    $this->postJson('/api/sync', [])->assertUnauthorized();
});

it('reports total sales as milling cash plus tahop income, with labor split among the operators', function () {
    $this->withToken($this->token)->postJson('/api/sync', pptSampleBatch($this->deviceUuid, $this->operator->id))->assertOk();

    $report = app(ReportService::class)->daily(today());
    $tahopRow = $report['rows']->first(fn (array $row) => $row['transaction']->type === 'tahop_sale');

    expect($report['totals']['milling_cash'])->toBe(176400)
        ->and($report['totals']['tahop_sales'])->toBe(96000)
        ->and($report['totals']['tahop_income'])->toBe(24000)
        ->and($report['totals']['tahop_cost'])->toBe(72000)
        ->and($report['totals']['total_sales'])->toBe(200400)
        ->and($report['totals']['fuel'])->toBe(20040)
        ->and($report['totals']['labor'])->toBe(54108)
        ->and($report['totals']['net'])->toBe(126252)
        ->and($tahopRow['sales'])->toBe(24000)
        ->and($tahopRow['shares'])->toBe(['Danilo C. Fabriga' => 2160, 'Joel Blagot' => 2160, 'Kcire John Flores' => 2160])
        ->and($report['totals']['operator_shares'])->toBe([
            'Danilo C. Fabriga' => 18036,
            'Joel Blagot' => 18036,
            'Kcire John Flores' => 18036,
        ]);
});

it('leaves days without End of Day out of the reports', function () {
    $batch = pptSampleBatch($this->deviceUuid, $this->operator->id);
    $batch['day_sessions'][0] = [
        ...$batch['day_sessions'][0],
        'status' => 'open', 'cash_count' => null, 'cash_counted' => null, 'cash_on_hand' => null, 'tahop_in_kind' => null,
        'total_collected' => null, 'total_sales' => null, 'variance' => null, 'closed_at' => null,
    ];
    $this->withToken($this->token)->postJson('/api/sync', $batch)->assertOk();

    $reports = app(ReportService::class);
    $daily = $reports->daily(today());

    expect($daily['rows'])->toHaveCount(0)
        ->and($daily['open_sessions'])->toHaveCount(1)
        ->and($reports->monthly(today())['open_days'])->toBe(1)
        ->and($reports->summary(today(), today())['total_sales'])->toBe(0)
        ->and($reports->tahopStock())->toBe(['kg_taken' => 48.0, 'kg_sold' => 48.0, 'kg_on_hand' => 0.0]);
});

it('rejects impossible numbers instead of crashing the database', function () {
    $batch = pptSampleBatch($this->deviceUuid, $this->operator->id);
    $batch['day_sessions'][0]['rice_rate'] = 99999999999;
    $batch['transactions'][0]['sacks'][0] = 5000;

    $this->withToken($this->token)->postJson('/api/sync', $batch)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['day_sessions.0.rice_rate', 'transactions.0.sacks.0']);
});

it('records who voided a transaction with the owner code', function () {
    $batch = pptSampleBatch($this->deviceUuid, $this->operator->id);
    $this->withToken($this->token)->postJson('/api/sync', $batch)->assertOk();

    $later = now()->addMinute()->toIso8601ZuluString('millisecond');
    $batch['transactions'][0] = [
        ...$batch['transactions'][0],
        'status' => 'void', 'void_reason' => 'Wrong kg entered', 'voided_at' => $later, 'voided_by' => $this->operator->id, 'updated_at' => $later,
    ];
    $this->withToken($this->token)->postJson('/api/sync', $batch)->assertOk();

    $voided = Transaction::query()->where('receipt_no', 'A-000001')->first();
    expect($voided->status)->toBe('void')
        ->and($voided->voided_by)->toBe($this->operator->id)
        ->and($voided->voider->is($this->operator))->toBeTrue();
});

it('sends phones only the hash of the void approval code', function () {
    $this->withToken($this->token)->postJson('/api/sync', pptSampleBatch($this->deviceUuid, $this->operator->id))
        ->assertOk()
        ->assertJsonPath('void_code', null);

    Setting::setVoidCode('246810');

    $response = $this->withToken($this->token)->postJson('/api/sync', pptSampleBatch($this->deviceUuid, $this->operator->id))->assertOk();
    $code = $response->json('void_code');

    expect($code['hash'])->toBe(hash('sha256', $code['salt'].':246810'))
        ->and($response->getContent())->not->toContain('246810');
});

it('records a reopened day with who, when, why and the old variance', function () {
    $batch = pptSampleBatch($this->deviceUuid, $this->operator->id);
    $batch['day_sessions'][0]['variance'] = -30000;
    $this->withToken($this->token)->postJson('/api/sync', $batch)->assertOk();

    $later = now()->addMinute()->toIso8601ZuluString('millisecond');
    $batch['day_sessions'][0] = [
        ...$batch['day_sessions'][0],
        'status' => 'open', 'cash_count' => null, 'cash_counted' => null, 'cash_on_hand' => null, 'tahop_in_kind' => null,
        'total_collected' => null, 'total_sales' => null, 'variance' => null, 'closed_at' => null,
        'reopen_count' => 1, 'reopened_at' => $later, 'reopened_by' => $this->operator->id,
        'reopen_reason' => 'Cash counted wrong', 'reopen_previous_variance' => -30000, 'updated_at' => $later,
    ];
    $this->withToken($this->token)->postJson('/api/sync', $batch)->assertOk();

    $session = DaySession::query()->where('uuid', $batch['day_sessions'][0]['uuid'])->first();
    expect($session->status)->toBe('open')
        ->and($session->variance)->toBeNull()
        ->and($session->reopen_count)->toBe(1)
        ->and($session->reopener->is($this->operator))->toBeTrue()
        ->and($session->reopen_reason)->toBe('Cash counted wrong')
        ->and($session->reopen_previous_variance)->toBe(-30000);

    $owner = User::query()->where('username', 'owner')->first();
    $this->actingAs($owner)
        ->withSession([EnsureSessionNotExpired::SESSION_KEY => now()->getTimestamp()])
        ->get('/closings/'.$session->id)
        ->assertOk()
        ->assertSee('Reopened 1 time after End of Day')
        ->assertSee('Cash counted wrong')
        ->assertSee('variance before: -₱300.00', false);
});

it('keeps reopen details when an older app version does not send them', function () {
    $batch = pptSampleBatch($this->deviceUuid, $this->operator->id);
    $this->withToken($this->token)->postJson('/api/sync', $batch)->assertOk();
    $session = DaySession::query()->where('uuid', $batch['day_sessions'][0]['uuid'])->first();
    $session->update(['reopen_count' => 2, 'reopened_at' => now(), 'reopened_by' => $this->operator->id, 'reopen_reason' => 'Recount']);

    $batch['day_sessions'][0]['updated_at'] = now()->addMinute()->toIso8601ZuluString('millisecond');
    $this->withToken($this->token)->postJson('/api/sync', $batch)->assertOk();

    expect($session->fresh()->reopen_count)->toBe(2)
        ->and($session->fresh()->reopened_by)->toBe($this->operator->id);
});
