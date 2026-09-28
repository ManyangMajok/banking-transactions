<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class AccountReconciliationService
{
    public function mismatches(): array
    {
        return DB::transaction(function () {
            $mismatches = [];
            foreach (BankAccount::withTrashed()->get() as $account) {
                $incoming = (int) Transaction::where('destination_account_id', $account->id)->sum('amount_minor');
                $outgoing = (int) Transaction::where('source_account_id', $account->id)->sum('amount_minor');
                if ($account->balance_minor !== $incoming - $outgoing) {
                    $mismatches[] = ['account' => $account->account_number, 'stored' => $account->balance_minor, 'expected' => $incoming - $outgoing];
                }
            }

            return $mismatches;
        });
    }
}
