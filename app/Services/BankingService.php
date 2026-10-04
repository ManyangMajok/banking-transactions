<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Loan;
use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BankingService
{
    /** All entry points share sorted account locks and the same transaction boundary. */
    public function execute(string $type, int $actor, ?int $source, ?int $destination, int $minor, string $key): array
    {
        $validShape = match ($type) {
            'deposit', 'loan_disbursement' => $source === null && $destination !== null,
            'withdrawal' => $source !== null && $destination === null,
            'transfer' => $source !== null && $destination !== null && $source !== $destination,
            default => false,
        };
        if (! $validShape || ! Str::isUuid($key)) {
            $this->reject('Choose valid, distinct accounts and an operation key.');
        }
        if ($type === 'loan_disbursement') {
            $minor = 1000000;
        }
        if ($minor <= 0 || $minor > config('banking.limit_minor')) {
            $this->reject('Amount exceeds the transaction limit.');
        }
        $key = strtolower($key);
        $hash = hash('sha256', implode('|', [$type, $actor, $source ?? '-', $destination ?? '-', $minor]));
        try {
            return DB::transaction(function () use ($type, $actor, $source, $destination, $minor, $key, $hash) {
                $ids = array_values(array_filter([$source, $destination], fn ($id) => $id !== null));
                sort($ids, SORT_NUMERIC);
                $accounts = [];
                foreach ($ids as $id) {
                    $accounts[$id] = BankAccount::withTrashed()->whereKey($id)->lockForUpdate()->first();
                }
                // Locking read sees a committed replay even under InnoDB REPEATABLE READ.
                $existing = Transaction::where('idempotency_key', $key)->lockForUpdate()->first();
                if ($existing) {
                    return $this->replay($existing, $hash);
                }
                foreach ($accounts as $account) {
                    if (! $account || $account->trashed()) {
                        $this->reject('An account no longer exists or has been deleted.');
                    }
                }
                if ($source !== null && $accounts[$source]->balance_minor < $minor) {
                    $this->reject('Insufficient available balance.');
                }
                if ($destination !== null && $accounts[$destination]->balance_minor > config('banking.limit_minor') - $minor) {
                    $this->reject('The recipient would exceed the account balance limit.');
                }
                if ($type === 'loan_disbursement' && Loan::where('bank_account_id', $destination)->exists()) {
                    $this->reject('This account has already received its permitted loan.');
                }
                $timestamp = now();
                if ($source !== null) {
                    $accounts[$source]->balance_minor -= $minor;
                    $accounts[$source]->last_activity_at = $timestamp;
                    $accounts[$source]->save();
                    $this->checkpoint('after_debit');
                }
                if ($destination !== null) {
                    $accounts[$destination]->balance_minor += $minor;
                    $accounts[$destination]->last_activity_at = $timestamp;
                    $accounts[$destination]->save();
                }
                $transaction = Transaction::create([
                    'reference' => (string) Str::uuid(), 'type' => $type,
                    'source_account_id' => $source, 'destination_account_id' => $destination,
                    'amount_minor' => $minor, 'performed_by' => $actor,
                    'idempotency_key' => $key, 'request_hash' => $hash, 'created_at' => $timestamp,
                ]);
                $this->checkpoint('after_history');
                if ($type === 'loan_disbursement') {
                    Loan::create(['loan_number' => 'LN'.Str::ulid(), 'bank_account_id' => $destination,
                        'principal_minor' => 1000000, 'outstanding_minor' => 1000000,
                        'disbursement_transaction_id' => $transaction->id, 'disbursed_at' => $timestamp]);
                }

                return ['transaction' => $transaction, 'replayed' => false];
            }, 3);
        } catch (QueryException $e) {
            // Only this named unique constraint means replay; all other failures propagate.
            if (($e->errorInfo[1] ?? null) !== 1062 || ! str_contains($e->getMessage(), 'transactions_idempotency_key_unique')) {
                throw $e;
            }

            return $this->replay(Transaction::where('idempotency_key', $key)->firstOrFail(), $hash);
        }
    }

    private function replay(Transaction $transaction, string $hash): array
    {
        if (! hash_equals($transaction->request_hash, $hash)) {
            $this->reject('This operation key was already used with different details. Start a new operation.');
        }

        return ['transaction' => $transaction, 'replayed' => true];
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['operation' => $message]);
    }

    /** Inert extension seam for deterministic rollback tests; never driven by request data. */
    protected function checkpoint(string $stage): void {}
}
