<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\DaySession;
use App\Models\Device;
use App\Models\Personnel;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Demo data for presentations: one operator, one phone (A), and the 14 days before today, all closed.
 * Today is left empty so a live demo on a phone starts clean.
 * Yesterday's first transaction is the exact sample from the FRT System PPT (₱1,764).
 *
 *   php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    private const LOCATIONS = ['Sta. Cruz', 'Mahayag', 'Salug', 'Tiparak', 'San Isidro', 'Kaangayan', 'Pugwan', 'Lourmah'];

    private const NAMES = [
        'Antonio Rivera', 'Nena Domingo', 'Linda Mercado', 'Ana Reyes', 'Ramon Bautista', 'Carlos Gonzales',
        'Maria Santos', 'Mark Salazar', 'Jose Mendoza', 'Rogelio Fernandez', 'Pedro Garcia', 'Layla Gay',
    ];

    private int $receipt = 0;

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $operator = User::query()->firstOrCreate(
            ['username' => 'daryl'],
            ['name' => 'Daryl Fabriga', 'role' => 'operator', 'is_active' => true, 'password' => 'password'],
        );

        $device = Device::register((string) Str::uuid(), 'Demo phone');
        $this->receipt = Transaction::query()->where('device_id', $device->id)->count();

        $customers = collect(self::NAMES)->map(fn (string $name, int $index) => Customer::query()->firstOrCreate(
            ['name' => $name],
            [
                'uuid' => (string) Str::uuid(),
                'device_id' => $device->id,
                'address' => self::LOCATIONS[$index % count(self::LOCATIONS)].', ZDS',
                'contact_no' => '0948'.str_pad((string) (7632531 + $index), 7, '0', STR_PAD_LEFT),
                'client_updated_at' => now(),
            ],
        ));

        $operators = Personnel::query()->whereIn('name', ['Danilo C. Fabriga', 'Joel Blagot', 'Kcire John Flores'])
            ->get(['id', 'name'])->map(fn (Personnel $p) => ['id' => $p->id, 'name' => $p->name])->all();

        for ($daysAgo = 14; $daysAgo >= 1; $daysAgo--) {
            $date = today()->subDays($daysAgo);
            $this->seedDay($device, $operator, $operators, $customers->all(), $date, $daysAgo === 1);
        }
    }

    /**
     * @param  list<array{id: int, name: string}>  $operators
     * @param  list<Customer>  $customers
     */
    private function seedDay(Device $device, User $operator, array $operators, array $customers, Carbon $date, bool $isPptSample): void
    {
        $session = DaySession::query()->create([
            'uuid' => (string) Str::uuid(),
            'device_id' => $device->id,
            'recorded_by' => $operator->id,
            'business_date' => $date->toDateString(),
            'status' => 'open',
            'rice_rate' => 400,
            'tahop_buy_rate' => 1500,
            'tahop_sell_rate' => 2000,
            'fuel_price' => 9500,
            'opening_fund' => 50000,
            'labor_pct' => 30,
            'fuel_pct' => 10,
            'operators' => $operators,
            'started_at' => $date->copy()->setTime(7, 0),
            'client_updated_at' => now(),
        ]);

        $time = $date->copy()->setTime(7, 30);
        $millingCount = $isPptSample ? random_int(3, 6) : random_int(4, 9);
        $cashCollected = 0;
        $tahopInKind = 0;
        $totalSales = 0;

        for ($i = 0; $i < $millingCount; $i++) {
            $sacks = $isPptSample && $i === 0
                ? [64, 65, 60, 59, 61, 60, 66, 62, 64, 60]
                : array_map(fn () => random_int(55, 70), range(1, random_int(1, 12)));
            $tahopKg = $isPptSample && $i === 0 ? 48 : (random_int(0, 2) === 0 ? random_int(10, 50) : 0);
            $customer = $customers[array_rand($customers)];

            $totalKg = array_sum($sacks);
            $gross = $totalKg * 400;
            $tahopOffset = min($tahopKg * 1500, $gross);
            $amountDue = $gross - $tahopOffset;
            $cash = $isPptSample && $i === 0 ? 200000 : (int) (ceil($amountDue / 10000) * 10000);
            [$fuel, $labor, $net] = $this->deductions($amountDue);

            $this->createTransaction($session, $device, $operator, $time, [
                'type' => Transaction::TYPE_MILLING,
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'location' => self::LOCATIONS[array_rand(self::LOCATIONS)],
                'sacks' => $sacks,
                'sack_count' => count($sacks),
                'total_kg' => $totalKg,
                'rate' => 400,
                'gross' => $gross,
                'tahop_kg' => $tahopKg,
                'tahop_rate' => 1500,
                'tahop_offset' => $tahopOffset,
                'amount_due' => $amountDue,
                'cash' => $cash,
                'change' => $cash - $amountDue,
                'labor' => $labor,
                'fuel' => $fuel,
                'net' => $net,
            ]);

            $cashCollected += $amountDue;
            $tahopInKind += $tahopOffset;
            $totalSales += $amountDue;
            $time->addMinutes(random_int(25, 70));
        }

        $tahopKgSold = random_int(20, 80);
        $tahopSale = $tahopKgSold * 2000;
        $tahopIncome = $tahopSale - $tahopKgSold * 1500;
        $tahopCash = (int) (ceil($tahopSale / 10000) * 10000);
        [$tahopFuel, $tahopLabor, $tahopNet] = $this->deductions($tahopIncome);

        $this->createTransaction($session, $device, $operator, $time, [
            'type' => Transaction::TYPE_TAHOP_SALE,
            'customer_name' => 'Walk-in buyer',
            'total_kg' => $tahopKgSold,
            'rate' => 2000,
            'gross' => $tahopSale,
            'tahop_rate' => 1500,
            'amount_due' => $tahopSale,
            'cash' => $tahopCash,
            'change' => $tahopCash - $tahopSale,
            'labor' => $tahopLabor,
            'fuel' => $tahopFuel,
            'net' => $tahopNet,
        ]);

        // Client rule: only the tahop income (not the whole tahop sale) is part of the expected cash.
        $cashCollected += $tahopIncome;
        $totalSales += $tahopIncome;

        $variance = random_int(0, 4) === 0 ? -random_int(1, 5) * 100 : 0;
        $cashCounted = $session->opening_fund + $cashCollected + $variance;

        $session->update([
            'status' => 'closed',
            'cash_count' => $this->breakdown($cashCounted),
            'cash_counted' => $cashCounted,
            'cash_on_hand' => $cashCounted - $session->opening_fund,
            'tahop_in_kind' => $tahopInKind,
            'total_collected' => $cashCollected,
            'total_sales' => $totalSales,
            'variance' => $variance,
            'remarks' => $variance ? 'Short on change — checked with operators.' : null,
            'closed_at' => $time->copy()->addHour(),
            'client_updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTransaction(DaySession $session, Device $device, User $operator, Carbon $time, array $attributes): void
    {
        $this->receipt++;

        Transaction::query()->create([
            'uuid' => (string) Str::uuid(),
            'device_id' => $device->id,
            'day_session_id' => $session->id,
            'recorded_by' => $operator->id,
            'receipt_no' => $device->code.'-'.str_pad((string) $this->receipt, 6, '0', STR_PAD_LEFT),
            'business_date' => $session->business_date,
            'status' => 'valid',
            'recorded_at' => $time->copy(),
            'client_updated_at' => now(),
            ...$attributes,
        ]);
    }

    /**
     * Diesel = 10% of sales; labor = 30% of (sales − diesel); net = the rest. Same rule as the app.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function deductions(int $sales): array
    {
        $fuel = (int) round($sales * 10 / 100);
        $labor = (int) round(($sales - $fuel) * 30 / 100);

        return [$fuel, $labor, $sales - $fuel - $labor];
    }

    /**
     * @return array<string, int>
     */
    private function breakdown(int $centavos): array
    {
        $pieces = [];

        foreach ([100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 100, 25] as $denomination) {
            $pieces[(string) $denomination] = intdiv($centavos, $denomination);
            $centavos -= $pieces[(string) $denomination] * $denomination;
        }

        return $pieces;
    }
}
