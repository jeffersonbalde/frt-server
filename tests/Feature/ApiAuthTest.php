<?php

use App\Models\Personnel;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed();
});

it('logs an operator in, registers the phone, and returns reference data', function () {
    User::factory()->create(['username' => 'daryl', 'password' => 'secret123']);

    $response = $this->postJson('/api/login', [
        'username' => 'daryl',
        'password' => 'secret123',
        'device_uuid' => (string) Str::uuid(),
        'device_name' => 'Samsung A15',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.username', 'daryl')
        ->assertJsonPath('user.role', 'operator')
        ->assertJsonPath('device.code', 'A')
        ->assertJsonPath('settings.rice_rate', 400)
        ->assertJsonPath('settings.tahop_buy_rate', 1500)
        ->assertJsonPath('settings.labor_pct', 30)
        ->assertJsonCount(Personnel::query()->count(), 'personnel')
        ->assertJsonStructure(['token', 'server_time', 'customers']);
});

it('gives the second phone code B and keeps the code for the same phone', function () {
    User::factory()->create(['username' => 'daryl', 'password' => 'secret123']);
    $first = (string) Str::uuid();

    $login = fn (string $uuid) => $this->postJson('/api/login', [
        'username' => 'daryl', 'password' => 'secret123', 'device_uuid' => $uuid,
    ]);

    $login($first)->assertJsonPath('device.code', 'A');
    $login((string) Str::uuid())->assertJsonPath('device.code', 'B');
    $login($first)->assertJsonPath('device.code', 'A');
});

it('rejects a wrong password', function () {
    User::factory()->create(['username' => 'daryl', 'password' => 'secret123']);

    $this->postJson('/api/login', [
        'username' => 'daryl', 'password' => 'wrong', 'device_uuid' => (string) Str::uuid(),
    ])->assertUnprocessable()->assertJsonValidationErrors('username');
});

it('rejects a deactivated account', function () {
    User::factory()->inactive()->create(['username' => 'daryl', 'password' => 'secret123']);

    $this->postJson('/api/login', [
        'username' => 'daryl', 'password' => 'secret123', 'device_uuid' => (string) Str::uuid(),
    ])->assertUnprocessable()->assertJsonValidationErrors('username');
});

it('revokes the phone token on logout', function () {
    User::factory()->create(['username' => 'daryl', 'password' => 'secret123']);

    $token = $this->postJson('/api/login', [
        'username' => 'daryl', 'password' => 'secret123', 'device_uuid' => (string) Str::uuid(),
    ])->json('token');

    $this->withToken($token)->postJson('/api/logout')->assertOk();

    expect(User::query()->where('username', 'daryl')->first()->tokens()->count())->toBe(0);
});
