<?php

use App\Http\Middleware\EnsureSessionNotExpired;
use App\Models\Customer;
use App\Models\DaySession;
use App\Models\Device;
use App\Models\Personnel;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->seed();
    $this->seed(DemoDataSeeder::class);
    User::query()->where('role', 'operator')->first()->createToken('device:A');
});

it('deletes the business records but keeps accounts, personnel and settings', function () {
    $users = User::query()->count();
    $personnel = Personnel::query()->count();
    $settings = Setting::values();
    expect(Transaction::query()->count())->toBeGreaterThan(0)
        ->and(DaySession::query()->count())->toBeGreaterThan(0);

    $this->artisan('frt:reset-data', ['--force' => true])
        ->expectsOutputToContain('back to zero')
        ->assertSuccessful();

    expect(Transaction::query()->count())->toBe(0)
        ->and(DaySession::query()->count())->toBe(0)
        ->and(Customer::query()->count())->toBe(0)
        ->and(Device::query()->count())->toBe(0)
        ->and(PersonalAccessToken::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe($users)
        ->and(Personnel::query()->count())->toBe($personnel)
        ->and(Setting::values())->toBe($settings);
});

it('can keep the customer list', function () {
    $customers = Customer::query()->count();

    $this->artisan('frt:reset-data', ['--force' => true, '--keep-customers' => true])->assertSuccessful();

    expect(Transaction::query()->count())->toBe(0)
        ->and(Customer::query()->count())->toBe($customers);
});

it('deletes nothing when the owner says no', function () {
    $transactions = Transaction::query()->count();

    $this->artisan('frt:reset-data')
        ->expectsConfirmation('This cannot be undone. Delete these records now?', 'no')
        ->assertFailed();

    expect(Transaction::query()->count())->toBe($transactions);
});

it('shows a dashboard with all zeros afterwards', function () {
    $this->artisan('frt:reset-data', ['--force' => true])->assertSuccessful();

    $owner = User::query()->where('username', 'owner')->first();
    $this->actingAs($owner)
        ->withSession([EnsureSessionNotExpired::SESSION_KEY => now()->getTimestamp()])
        ->get('/')
        ->assertOk()
        ->assertSee('₱0.00');
});
