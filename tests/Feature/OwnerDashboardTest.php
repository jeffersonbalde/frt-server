<?php

use App\Http\Middleware\EnsureSessionNotExpired;
use App\Models\Customer;
use App\Models\DaySession;
use App\Models\Personnel;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed();
    $this->owner = User::query()->where('username', 'owner')->first();
    // A signed-in dashboard session carries its sign-in time; actingAs() alone does not set it.
    $this->withSession([EnsureSessionNotExpired::SESSION_KEY => now()->getTimestamp()]);
});

it('sends guests to the login page', function () {
    $this->get('/')->assertRedirect('/login');
});

it('logs the owner in', function () {
    $this->post('/login', ['username' => 'owner', 'password' => 'password'])->assertRedirect('/');
    $this->assertAuthenticatedAs($this->owner);
});

it('does not offer "keep me logged in"', function () {
    $this->get('/login')->assertOk()->assertDontSee('name="remember"', false);
});

it('asks the owner to log in again 8 hours after signing in', function () {
    $this->post('/login', ['username' => 'owner', 'password' => 'password'])->assertRedirect('/');
    $this->get('/')->assertOk();

    $this->travel(7)->hours();
    $this->get('/')->assertOk();

    $this->travel(61)->minutes();
    $this->get('/')->assertRedirect('/login')->assertSessionHasErrors('username');
    $this->assertGuest();
});

it('ends dashboard sessions that have no sign-in time', function () {
    $this->withSession([EnsureSessionNotExpired::SESSION_KEY => null])
        ->actingAs($this->owner)
        ->get('/')
        ->assertRedirect('/login');
});

it('keeps operators out of the dashboard', function () {
    User::factory()->create(['username' => 'daryl', 'password' => 'secret123']);

    $this->post('/login', ['username' => 'daryl', 'password' => 'secret123'])->assertSessionHasErrors('username');
    $this->assertGuest();

    $this->actingAs(User::query()->where('username', 'daryl')->first())->get('/')->assertForbidden();
});

it('renders every owner page with demo data', function () {
    $this->seed(DemoDataSeeder::class);
    $session = DaySession::query()->where('status', 'closed')->first();
    $customer = Transaction::query()->whereNotNull('customer_id')->first()->customer;

    $pages = [
        '/', '/reports/daily', '/reports/daily?date='.$session->business_date->toDateString(), '/reports/monthly',
        '/closings', '/closings?status=variance', '/closings/'.$session->id, '/customers', '/customers?q=Rivera',
        '/customers/'.$customer->id, '/personnel', '/personnel/create', '/personnel/1/edit', '/users', '/users/create',
        '/users/'.$this->owner->id.'/edit', '/devices', '/settings', '/voids', '/voids?from=2026-01-01&to=2026-12-31',
    ];

    foreach ($pages as $page) {
        $this->actingAs($this->owner)->get($page)->assertOk();
    }
});

it('numbers table rows, continuing across pages', function () {
    foreach (range(1, 30) as $i) {
        Customer::query()->create(['uuid' => (string) Str::uuid(), 'name' => sprintf('Customer %02d', $i), 'client_updated_at' => now()]);
    }

    $this->actingAs($this->owner)->get('/customers')
        ->assertOk()
        ->assertSee('<th class="col-no">#</th>', false)
        ->assertSeeInOrder(['<td class="col-no">1</td>', 'Customer 01', '<td class="col-no">25</td>', 'Customer 25'], false)
        ->assertDontSee('Customer 26');

    $this->actingAs($this->owner)->get('/customers?page=2')
        ->assertOk()
        ->assertSeeInOrder(['<td class="col-no">26</td>', 'Customer 26', '<td class="col-no">30</td>', 'Customer 30'], false);
});

it('shows the PPT sample yesterday and leaves today empty for a live demo', function () {
    $this->seed(DemoDataSeeder::class);

    $this->actingAs($this->owner)->get('/reports/daily')
        ->assertOk()
        ->assertSee('No synced transactions for this date.');

    $this->actingAs($this->owner)->get('/reports/daily?date='.today()->subDay()->toDateString())
        ->assertOk()
        ->assertSee('A-')
        ->assertSee('621.00')
        ->assertSee('₱2,484.00')
        ->assertSee('(720.00)')
        ->assertSee('₱236.00');
});

it('exports the daily and monthly reports as CSV', function () {
    $this->seed(DemoDataSeeder::class);

    $yesterday = today()->subDay()->toDateString();
    $daily = $this->actingAs($this->owner)->get('/reports/daily/export?date='.$yesterday);
    $daily->assertOk()->assertDownload('frt-daily-'.$yesterday.'.csv');
    expect($daily->streamedContent())->toContain('Trans. No')->toContain('Recorded By')->toContain('2484.00');

    $monthly = $this->actingAs($this->owner)->get('/reports/monthly/export');
    $monthly->assertOk();
    expect($monthly->streamedContent())->toContain('Total Qty. Kg');
});

it('saves settings in centavos', function () {
    $this->actingAs($this->owner)->put('/settings', [
        'business_name' => 'Fabriga Rice Trading',
        'business_address' => 'P-2 Sta. Cruz, Mahayag, Z.D.S.',
        'business_contact' => '09387252257',
        'messenger' => 'Daryl Fabriga',
        'rice_rate' => '4.50',
        'tahop_buy_rate' => '15',
        'tahop_sell_rate' => '20',
        'fuel_price' => '95',
        'labor_pct' => '30',
        'fuel_pct' => '10',
    ])->assertRedirect('/settings');

    expect(Setting::values()['rice_rate'])->toBe(450);
});

it('rejects deductions above 100 percent', function () {
    $this->actingAs($this->owner)->put('/settings', [
        'business_name' => 'FRT', 'business_address' => 'ZDS', 'business_contact' => '0900',
        'rice_rate' => 4, 'tahop_buy_rate' => 15, 'tahop_sell_rate' => 20, 'fuel_price' => 95,
        'labor_pct' => 95, 'fuel_pct' => 10,
    ])->assertSessionHasErrors('labor_pct');
});

it('manages personnel', function () {
    $this->actingAs($this->owner)->post('/personnel', [
        'name' => 'New Helper', 'position' => 'Operations / Milling Personnel', 'is_active' => '1',
    ])->assertRedirect('/personnel');

    $person = Personnel::query()->where('name', 'New Helper')->first();
    expect($person->is_active)->toBeTrue();

    $this->actingAs($this->owner)->put('/personnel/'.$person->id, [
        'name' => 'New Helper', 'position' => 'Operations / Milling Personnel', 'is_active' => '0',
    ])->assertRedirect('/personnel');

    expect($person->fresh()->is_active)->toBeFalse();
});

it('creates and deactivates an operator account, revoking phone tokens', function () {
    $this->actingAs($this->owner)->post('/users', [
        'name' => 'Jerry Suezo', 'username' => 'jerry', 'role' => 'operator',
        'password' => 'secret123', 'password_confirmation' => 'secret123',
    ])->assertRedirect('/users');

    $jerry = User::query()->where('username', 'jerry')->first();
    $jerry->createToken('device:A');

    $this->actingAs($this->owner)->put('/users/'.$jerry->id, [
        'name' => 'Jerry Suezo', 'username' => 'jerry', 'role' => 'operator', 'is_active' => '0',
    ])->assertRedirect('/users');

    expect($jerry->fresh()->is_active)->toBeFalse()
        ->and($jerry->tokens()->count())->toBe(0);
});

it('does not let the owner deactivate their own account', function () {
    $this->actingAs($this->owner)->put('/users/'.$this->owner->id, [
        'name' => $this->owner->name, 'username' => 'owner', 'role' => 'owner', 'is_active' => '0',
    ])->assertSessionHasErrors('role');

    expect($this->owner->fresh()->is_active)->toBeTrue();
});

it('accepts prices typed with thousands commas', function () {
    $this->actingAs($this->owner)->put('/settings', [
        'business_name' => 'Fabriga Rice Trading', 'business_address' => 'ZDS', 'business_contact' => '0900',
        'rice_rate' => '4.00', 'tahop_buy_rate' => '15', 'tahop_sell_rate' => '20', 'fuel_price' => '1,250.50',
        'labor_pct' => '30', 'fuel_pct' => '10',
    ])->assertRedirect('/settings')->assertSessionHasNoErrors();

    expect(Setting::values()['fuel_price'])->toBe(125050);
});

it('explains settings mistakes in plain words', function () {
    $this->actingAs($this->owner)->put('/settings', [
        'business_name' => 'FRT', 'business_address' => 'ZDS', 'business_contact' => '0900',
        'rice_rate' => '0', 'tahop_buy_rate' => '15.555', 'tahop_sell_rate' => '20,000', 'fuel_price' => 'abc',
        'labor_pct' => '30', 'fuel_pct' => '10',
    ])->assertSessionHasErrors([
        'rice_rate' => 'The rice price per kg must be more than zero.',
        'tahop_buy_rate' => 'The tahop buying price can have at most 2 decimal places.',
        'tahop_sell_rate' => 'The tahop selling price is too high (maximum ₱10,000).',
        'fuel_price' => 'The oil/gas price must be a number, e.g. 1,250.50.',
    ]);
});

it('lets the owner set the void approval code', function () {
    $this->actingAs($this->owner)->get('/settings')->assertOk()->assertSee('No code yet');

    $this->actingAs($this->owner)->put('/settings/void-code', ['void_code' => '12345', 'void_code_confirmation' => '12345'])
        ->assertSessionHasErrors(['void_code' => 'The approval code must be exactly 6 digits.']);
    $this->actingAs($this->owner)->put('/settings/void-code', ['void_code' => '123456', 'void_code_confirmation' => '654321'])
        ->assertSessionHasErrors(['void_code' => 'The two codes do not match.']);
    expect(Setting::voidCode())->toBeNull();

    $this->actingAs($this->owner)->put('/settings/void-code', ['void_code' => '246810', 'void_code_confirmation' => '246810'])
        ->assertRedirect('/settings');

    $code = Setting::voidCode();
    expect($code['hash'])->toBe(hash('sha256', $code['salt'].':246810'));
    $this->actingAs($this->owner)->get('/settings')->assertSee('A code is set');
});

it('lists voided transactions with the reason, time and who voided them', function () {
    $this->seed(DemoDataSeeder::class);
    $operator = User::factory()->create(['name' => 'Jerry Suezo']);
    $transaction = Transaction::query()->where('status', 'valid')->first();
    $transaction->update(['status' => 'void', 'void_reason' => 'Customer cancelled', 'voided_at' => now(), 'voided_by' => $operator->id]);

    $this->actingAs($this->owner)->get('/voids')
        ->assertOk()
        ->assertSee($transaction->receipt_no)
        ->assertSee('Customer cancelled')
        ->assertSee('Jerry Suezo')
        ->assertSee('Owner code');

    $this->actingAs($this->owner)->get('/reports/daily?date='.$transaction->business_date->toDateString())
        ->assertOk()
        ->assertSee('Void: Customer cancelled')
        ->assertSee('by Jerry Suezo')
        ->assertSee('Recorded By')
        ->assertSee($transaction->recorder->name);
});

it('builds https links behind the hosting proxy (no mixed content)', function () {
    $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'frt.example.com', 'X-Forwarded-Port' => '443'])
        ->get('/login')
        ->assertOk()
        ->assertSee('action="https://frt.example.com/login"', false)
        ->assertSee('https://frt.example.com/images/logo-mark.png', false)
        ->assertDontSee('http://frt.example.com', false);
});
