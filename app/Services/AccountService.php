<?php

namespace App\Services;

use App\Models\BankAccount;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountService
{
    public function create(string $name): BankAccount
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return BankAccount::create(['customer_name' => $name, 'account_number' => 'KE'.Str::ulid(), 'balance_minor' => 0]);
            } catch (QueryException $e) {
                if (($e->errorInfo[1] ?? null) !== 1062 || ! str_contains($e->getMessage(), 'bank_accounts_account_number_unique') || $attempt === 2) {
                    throw $e;
                }
            }
        }
        throw new \LogicException('Account number retry exhausted.');
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $account = BankAccount::withTrashed()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($account->trashed()) {
                return;
            }
            if ($reason = $account->deletionReason()) {
                throw ValidationException::withMessages(['operation' => $reason]);
            }
            $account->delete();
        }, 3);
    }
}
