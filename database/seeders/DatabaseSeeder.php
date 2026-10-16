<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Personnel;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the owner account, the FRT personnel from the capstone (Ch. 1 organizational structure),
     * and the default settings. Safe to run more than once.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['username' => config('frt.owner.username')],
            [
                'name' => config('frt.owner.name'),
                'role' => Role::Owner,
                'is_active' => true,
                'password' => config('frt.owner.password'),
            ],
        );

        $personnel = [
            ['Danilo C. Fabriga', 'Driver / Equipment Operator'],
            ['Jerry Suezo', 'Driver / Equipment Operator'],
            ['Joel Blagot', 'Operations / Milling Personnel'],
            ['Kcire John Flores', 'Operations / Milling Personnel'],
            ['Cyril John Flores', 'Operations / Milling Personnel'],
            ['Ariel Jay Delacuesta', 'Operations / Milling Personnel'],
        ];

        foreach ($personnel as [$name, $position]) {
            Personnel::query()->firstOrCreate(['name' => $name], ['position' => $position, 'is_active' => true]);
        }

        foreach (Setting::DEFAULTS as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => (string) $value]);
        }
    }
}
