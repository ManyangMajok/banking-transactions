<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BankingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $staff = User::firstOrCreate(['email' => 'staff@example.test'], ['name' => 'Demo Staff', 'password' => Hash::make('DemoBanking!2026'), 'email_verified_at' => now()]);
            $names = ['Amara Njeri', 'Brian Otieno', 'Chao Mwangi', 'Dalia Wanjiku', 'Eli Kamau', 'Farah Achieng'];
            foreach ($names as $i => $name) {
                $number = 'KE-DEMO-'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT);
                if (BankAccount::withTrashed()->where('account_number', $number)->exists()) {
                    continue;
                }
                $account = BankAccount::create(['account_number' => $number, 'customer_name' => $name]);
                if (in_array($i, [0, 1, 4])) {
                    app(BankingService::class)->execute('deposit', $staff->id, null, $account->id, [0 => 2500000, 1 => 750000, 4 => 320000][$i], (string) Str::uuid());
                }
                if ($i === 5) {
                    app(BankingService::class)->execute('loan_disbursement', $staff->id, null, $account->id, 1000000, (string) Str::uuid());
                }
                if (in_array($i, [3, 4])) {
                    $old = now()->subMonthsNoOverflow(15);
                    $account->refresh()->update(['created_at' => $old, 'last_activity_at' => $i === 4 ? $old : null]);
                    Transaction::where('destination_account_id', $account->id)->update(['created_at' => $old]);
                }
            }
        });
    }
}
