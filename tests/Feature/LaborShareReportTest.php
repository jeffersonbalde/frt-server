<?php

use App\Http\Middleware\EnsureSessionNotExpired;
use App\Models\DaySession;
use App\Models\Device;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReportService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed();
    $this->travelTo(Carbon::parse('2026-10-20 10:00'));
    $this->owner = User::query()->where('role', 'owner')->first();
    $this->device = Device::register((string) Str::uuid(), 'Test phone');
    $this->withSession([EnsureSessionNotExpired::SESSION_KEY => now()->getTimestamp()]);
});

/**
 * A day on one phone with the given operators on duty and one milling transaction per labor amount (centavos).
 *
 * @param  list<string>  $operators
 * @param  list<int>  $labors
 */
function laborDay(Device $device, string $date, array $operators, array $labors, string $status = 'closed', string $txStatus = 'valid'): DaySession
{
    $session = DaySession::query()->create([
        'uuid' => (string) Str::uuid(), 'device_id' => $device->id, 'business_date' => $date, 'status' => $status,
        'rice_rate' => 400, 'tahop_buy_rate' => 1500, 'tahop_sell_rate' => 2000, 'fuel_price' => 9500,
        'opening_fund' => 0, 'labor_pct' => 30, 'fuel_pct' => 10,
        'operators' => array_map(fn (string $name, int $id) => ['id' => $id + 1, 'name' => $name], $operators, array_keys($operators)),
        'started_at' => $date.' 07:00:00', 'client_updated_at' => now(),
    ]);

    foreach ($labors as $index => $labor) {
        Transaction::query()->create([
            'uuid' => (string) Str::uuid(), 'device_id' => $device->id, 'day_session_id' => $session->id,
            'receipt_no' => $device->code.'-'.Str::random(6), 'business_date' => $date, 'status' => $txStatus,
            'type' => Transaction::TYPE_MILLING, 'customer_name' => 'Customer', 'sacks' => [50], 'sack_count' => 1,
            'total_kg' => 50, 'rate' => 400, 'gross' => 20000, 'tahop_kg' => 0, 'tahop_rate' => 1500, 'tahop_offset' => 0,
            'amount_due' => 20000, 'cash' => 20000, 'change' => 0, 'labor' => $labor, 'fuel' => 2000, 'net' => 18000 - $labor,
            'recorded_at' => $date.' 08:'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).':00', 'client_updated_at' => now(),
        ]);
    }

    return $session;
}

it('adds up each operator\'s labor share between two dates', function () {
    laborDay($this->device, '2026-10-16', ['Ariel', 'Jerry'], [5400, 1001]);   // 6,401 → 3,201 + 3,200
    laborDay($this->device, '2026-10-17', ['Ariel', 'Danilo', 'Jerry'], [9000]); // 3,000 each
    laborDay($this->device, '2026-10-18', ['Ariel'], [4000], txStatus: 'void');  // voided: not counted
    laborDay($this->device, '2026-10-15', ['Ariel'], [7000]);                  // before the range

    $report = app(ReportService::class)->laborShares(Carbon::parse('2026-10-16'), Carbon::parse('2026-10-31'));

    expect($report['operators'])->toBe([
        ['name' => 'Ariel', 'days' => 3, 'final' => 6201, 'pending' => 0, 'total' => 6201],
        ['name' => 'Danilo', 'days' => 1, 'final' => 3000, 'pending' => 0, 'total' => 3000],
        ['name' => 'Jerry', 'days' => 2, 'final' => 6200, 'pending' => 0, 'total' => 6200],
    ])
        ->and($report['totals'])->toBe(['final' => 15401, 'pending' => 0, 'total' => 15401])
        ->and($report['open_dates'])->toBe([]);

    // Every centavo of labor is shared out: the total equals the labor of the valid transactions.
    expect($report['totals']['total'])->toBe(5400 + 1001 + 9000);
});

it('includes days not yet closed but marks them not final', function () {
    laborDay($this->device, '2026-10-19', ['Ariel', 'Jerry'], [6000]);
    laborDay($this->device, '2026-10-20', ['Ariel', 'Jerry'], [2000], status: 'open');

    $report = app(ReportService::class)->laborShares(Carbon::parse('2026-10-16'), Carbon::parse('2026-10-31'));

    expect($report['totals'])->toBe(['final' => 6000, 'pending' => 2000, 'total' => 8000])
        ->and($report['operators'][0])->toMatchArray(['name' => 'Ariel', 'final' => 3000, 'pending' => 1000])
        ->and(array_map(fn ($date) => $date->toDateString(), $report['open_dates']))->toBe(['2026-10-20'])
        ->and(array_column($report['days'], 'final'))->toBe([true, false]);

    $this->actingAs($this->owner)->get('/reports/labor?from=2026-10-16&to=2026-10-31')
        ->assertOk()
        ->assertSee('1 day not yet closed (Oct 20)')
        ->assertSee('Not final');
});

it('matches the labor shares on the Monthly Report', function () {
    $this->seed(DemoDataSeeder::class);
    $month = Carbon::parse('2026-10-01');

    $monthly = app(ReportService::class)->monthly($month)['operators'];
    $labor = app(ReportService::class)->laborShares($month, $month->copy()->endOfMonth());

    expect(collect($labor['operators'])->mapWithKeys(fn ($operator) => [$operator['name'] => $operator['final']])->filter()->all())
        ->toBe($monthly->map(fn ($operator) => $operator['share'])->filter()->all());
});

it('defaults to the current kinsenas and offers quick ranges', function () {
    $response = $this->actingAs($this->owner)->get('/reports/labor')->assertOk();

    expect($response->viewData('from')->toDateString())->toBe('2026-10-16')
        ->and($response->viewData('to')->toDateString())->toBe('2026-10-31');

    $ranges = collect($response->viewData('presets'))->map(fn ($preset) => array_map(fn ($date) => $date->toDateString(), $preset['range']));
    expect($ranges->all())->toBe([
        'this_kinsenas' => ['2026-10-16', '2026-10-31'],
        'last_kinsenas' => ['2026-10-01', '2026-10-15'],
        'this_week' => ['2026-10-19', '2026-10-25'],
        'last_week' => ['2026-10-12', '2026-10-18'],
        'this_month' => ['2026-10-01', '2026-10-31'],
        'last_month' => ['2026-09-01', '2026-09-30'],
    ]);
});

it('accepts any From and To dates and rejects a To date before From', function () {
    laborDay($this->device, '2026-10-03', ['Ariel'], [1500]);

    $this->actingAs($this->owner)->get('/reports/labor?from=2026-10-03&to=2026-10-03')
        ->assertOk()
        ->assertSee('Oct 3, 2026')
        ->assertSee('Ariel');

    $this->actingAs($this->owner)->from('/reports/labor')->get('/reports/labor?from=2026-10-10&to=2026-10-01')
        ->assertRedirect('/reports/labor')
        ->assertSessionHasErrors('to');
});

it('exports the labor shares as an Excel file', function () {
    laborDay($this->device, '2026-10-16', ['Ariel', 'Jerry'], [6000]);

    $this->actingAs($this->owner)->get('/reports/labor/export?from=2026-10-16&to=2026-10-31')
        ->assertOk()
        ->assertDownload('frt-labor-2026-10-16-to-2026-10-31.xlsx');
});

it('keeps the labor page for the owner only', function () {
    $this->get('/reports/labor')->assertRedirect('/login');
});
