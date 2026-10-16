<?php

use App\Models\Device;
use App\Services\ReportService;

it('splits labor equally and gives leftover centavos to the first operators', function () {
    expect(ReportService::splitShares(74520, 3))->toBe([24840, 24840, 24840])
        ->and(ReportService::splitShares(100, 3))->toBe([34, 33, 33])
        ->and(ReportService::splitShares(500, 0))->toBe([]);
});

it('gives each phone a letter code', function () {
    expect(Device::codeFor(0))->toBe('A')
        ->and(Device::codeFor(1))->toBe('B')
        ->and(Device::codeFor(25))->toBe('Z')
        ->and(Device::codeFor(26))->toBe('AA')
        ->and(Device::codeFor(27))->toBe('AB');
});

it('formats centavos as pesos', function () {
    expect(money(176400))->toBe('₱1,764.00')
        ->and(money(-1500))->toBe('-₱15.00')
        ->and(money(72000, false))->toBe('720.00')
        ->and(money_plain(23600))->toBe('236.00');
});
