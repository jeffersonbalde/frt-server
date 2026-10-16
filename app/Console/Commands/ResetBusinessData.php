<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\DaySession;
use App\Models\Device;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class ResetBusinessData extends Command
{
    /**
     * Staff accounts that stay after a reset.
     *
     * @var list<string>
     */
    private const KEPT_USERNAMES = ['admin', 'daryl'];

    /**
     * @var string
     */
    protected $signature = 'frt:reset-data
                            {--keep-customers : Keep the customer list (CIS)}
                            {--force : Do not ask for confirmation}';

    /**
     * @var string
     */
    protected $description = 'Start from zero: delete all transactions, End of Day records, customers, registered phones, and staff accounts except admin and daryl. Personnel and settings are kept.';

    public function handle(): int
    {
        $keepCustomers = (bool) $this->option('keep-customers');

        $this->table(['Will be deleted', 'Count'], array_filter([
            ['Transactions (receipts)', Transaction::query()->count()],
            ['Days (Start / End of Day)', DaySession::query()->count()],
            $keepCustomers ? null : ['Customers (CIS)', Customer::query()->count()],
            ['Registered phones (A, B, …)', Device::query()->count()],
            ['Phone sign-ins', PersonalAccessToken::query()->count()],
            ['Accounts (except admin and daryl)', User::query()->whereNotIn('username', self::KEPT_USERNAMES)->count()],
        ]));
        $this->line('Kept: admin (Danila Nice Fabriga) and daryl (Daryl Fabriga), personnel, settings and the owner approval code.');

        if (! $this->option('force') && ! $this->confirm('This cannot be undone. Delete these records now?')) {
            $this->warn('Nothing was deleted.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($keepCustomers) {
            Transaction::query()->delete();
            DaySession::query()->delete();
            if (! $keepCustomers) {
                Customer::query()->delete();
            }
            PersonalAccessToken::query()->delete();
            Device::query()->delete();
            User::query()->whereNotIn('username', self::KEPT_USERNAMES)->delete();
            DB::table('sessions')->where(function ($query) {
                $query->whereNull('user_id')
                    ->orWhereNotIn('user_id', User::query()->select('id'));
            })->delete();
            // Phones that still hold old data replace their customer list on their next sync.
            Setting::renewDatabaseId();
        });

        $this->info('Done. The system is back to zero.');
        $this->line('On every phone: clear the FRT app\'s data (Settings → Apps → FRT System → Storage → Clear data), then log in again.');

        return self::SUCCESS;
    }
}
