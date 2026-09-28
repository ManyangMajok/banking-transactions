<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountReconciliationService;
use App\Services\AccountService;
use App\Services\BankingService;
use App\Services\MoneyParser;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BankingTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->staff = User::factory()->create();
        $this->actingAs($this->staff);
    }

    private function account(string $name = 'Fictional Customer'): BankAccount
    {
        return app(AccountService::class)->create($name);
    }

    private function move(string $type, ?int $source, ?int $destination, int $minor, ?string $key = null): array
    {
        return app(BankingService::class)->execute($type, $this->staff->id, $source, $destination, $minor, $key ?? (string) Str::uuid());
    }

    private function rejects(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected a business validation rejection.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_accounts_are_unique_zero_and_input_is_whitelisted(): void
    {
        $a = $this->account();
        $b = $this->account();
        $this->assertNotSame($a->account_number, $b->account_number);
        $this->assertSame(0, $a->balance_minor);
        foreach (['', str_repeat('x', 121)] as $name) {
            $this->post('/accounts', ['customer_name' => $name])->assertSessionHasErrors('customer_name');
        }
        $this->post('/accounts', ['customer_name' => 'Customer', 'balance_minor' => 100])->assertSessionHasErrors('balance_minor');
        $this->post('/accounts', ['customer_name' => 'New Customer'])->assertRedirect();
        $this->assertDatabaseHas('bank_accounts', ['customer_name' => 'New Customer', 'balance_minor' => 0]);
        $this->get('/accounts?search=New')->assertOk();
        $this->get('/accounts/'.$a->id)->assertOk();
    }

    public function test_decimal_parser_accepts_exact_cents_and_limits(): void
    {
        foreach (['0.01' => 1, '1' => 100, '1.2' => 120, '1.20' => 120, ' 2.30 ' => 230, '100000000.00' => 10000000000] as $input => $expected) {
            $this->assertSame($expected, app(MoneyParser::class)->parse((string) $input));
        }
        foreach (['0', '-1', '+1', '1e2', '1,000', '.01', '1.', '1.234', '100000000.01', str_repeat('9', 99), 'NaN'] as $input) {
            $this->rejects(fn () => app(MoneyParser::class)->parse($input));
        }
    }

    public function test_deposits_withdrawals_and_failed_activity(): void
    {
        $a = $this->account();
        $a->update(['created_at' => now()->subYears(2)]);
        $this->post('/accounts/'.$a->id.'/deposits', ['amount' => '10.25', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertSame(1025, $a->fresh()->balance_minor);
        $this->assertNotNull($a->fresh()->last_activity_at);
        $before = $a->fresh()->last_activity_at;
        $this->travel(1)->days();
        $this->rejects(fn () => $this->move('withdrawal', $a->id, null, 1026));
        $this->assertTrue($before->equalTo($a->fresh()->last_activity_at));
        $this->move('withdrawal', $a->id, null, 1025);
        $this->assertSame(0, $a->fresh()->balance_minor);
        $this->assertSame(2, Transaction::count());
        $this->assertSame([], app(AccountReconciliationService::class)->mismatches());
    }

    public function test_invalid_http_amounts_write_nothing(): void
    {
        $a = $this->account();
        foreach (['0', '-1', '1.234', '1e3', '100000000.01', 25] as $amount) {
            $this->post('/accounts/'.$a->id.'/deposits', ['amount' => $amount, 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('amount');
        }
        $this->assertSame(0, Transaction::count());
        $this->assertNull($a->fresh()->last_activity_at);
    }

    public function test_transfer_conserves_money_and_records_one_movement(): void
    {
        $a = $this->account();
        $b = $this->account();
        $this->move('deposit', null, $a->id, 500000);
        $this->move('transfer', $a->id, $b->id, 150050);
        $this->assertSame(349950, $a->fresh()->balance_minor);
        $this->assertSame(150050, $b->fresh()->balance_minor);
        $this->assertSame(1, Transaction::where('type', 'transfer')->count());
        $this->assertNotNull($b->fresh()->last_activity_at);
        $this->assertSame([], app(AccountReconciliationService::class)->mismatches());
    }

    public function test_invalid_transfers_and_overflow_have_no_side_effects(): void
    {
        $a = $this->account();
        $b = $this->account();
        $deleted = $this->account();
        $deleted->delete();
        $this->move('deposit', null, $a->id, 100);
        $this->move('deposit', null, $b->id, 10000000000);
        foreach ([$a->id, $b->id, $deleted->id, 9999999] as $id) {
            $this->rejects(fn () => $this->move('transfer', $a->id, $id, 1));
        }
        $this->assertSame(100, $a->fresh()->balance_minor);
        $this->assertSame(2, Transaction::count());
    }

    public function test_transfer_rolls_back_after_debit(): void
    {
        $a = $this->account();
        $b = $this->account();
        $this->move('deposit', null, $a->id, 10000);
        $before = $a->fresh()->last_activity_at;
        $service = new class extends BankingService
        {
            protected function checkpoint(string $stage): void
            {
                if ($stage === 'after_debit') {
                    throw new \RuntimeException('Injected failure');
                }
            }
        };
        $this->travel(1)->days();
        try {
            $service->execute('transfer', $this->staff->id, $a->id, $b->id, 1000, Str::uuid());
            $this->fail();
        } catch (\RuntimeException $e) {
            $this->assertSame('Injected failure', $e->getMessage());
        }
        $this->assertSame(10000, $a->fresh()->balance_minor);
        $this->assertSame(0, $b->fresh()->balance_minor);
        $this->assertTrue($before->equalTo($a->fresh()->last_activity_at));
        $this->assertSame(1, Transaction::count());
    }

    public function test_replays_compare_canonical_payload_and_actor(): void
    {
        $a = $this->account();
        $b = $this->account();
        $key = (string) Str::uuid();
        $one = $this->move('deposit', null, $a->id, 100, $key);
        $two = $this->move('deposit', null, $a->id, 100, strtoupper($key));
        $this->assertTrue($two['replayed']);
        $this->assertSame($one['transaction']->reference, $two['transaction']->reference);
        $this->rejects(fn () => $this->move('deposit', null, $a->id, 101, $key));
        $this->rejects(fn () => $this->move('deposit', null, $b->id, 100, $key));
        $actor = User::factory()->create();
        $this->rejects(fn () => app(BankingService::class)->execute('deposit', $actor->id, null, $a->id, 100, $key));
        $this->assertSame(100, $a->fresh()->balance_minor);
        $this->assertSame(1, Transaction::count());
        $retry = (string) Str::uuid();
        $this->rejects(fn () => $this->move('withdrawal', $a->id, null, 200, $retry));
        $this->move('deposit', null, $a->id, 200);
        $this->move('withdrawal', $a->id, null, 200, $retry);
    }

    public function test_fixed_loan_replay_new_key_and_atomic_failure(): void
    {
        $a = $this->account();
        $key = (string) Str::uuid();
        $this->move('loan_disbursement', null, $a->id, 1, $key);
        $this->assertSame(1000000, $a->fresh()->balance_minor);
        $this->assertSame(1000000, Loan::first()->outstanding_minor);
        $this->assertSame(Transaction::first()->id, Loan::first()->disbursement_transaction_id);
        $this->assertTrue($this->move('loan_disbursement', null, $a->id, 1, $key)['replayed']);
        $this->rejects(fn () => $this->move('loan_disbursement', null, $a->id, 1));
        $b = $this->account();
        $service = new class extends BankingService
        {
            protected function checkpoint(string $stage): void
            {
                if ($stage === 'after_history') {
                    throw new \RuntimeException('Injected loan failure');
                }
            }
        };
        try {
            $service->execute('loan_disbursement', $this->staff->id, null, $b->id, 1000000, Str::uuid());
            $this->fail();
        } catch (\RuntimeException $e) {
            $this->assertSame('Injected loan failure', $e->getMessage());
        }
        $this->assertSame(0, $b->fresh()->balance_minor);
        $this->assertNull($b->fresh()->last_activity_at);
        $this->assertSame(1, Loan::count());
        $this->assertSame(1, Transaction::count());
    }

    public function test_dormancy_calendar_boundaries_and_reactivation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2024-02-29 12:00:00', 'UTC'));
        $a = $this->account();
        $a->update(['created_at' => '2023-02-28 12:00:00']);
        $this->assertTrue($a->fresh()->dormant());
        $a->update(['created_at' => '2023-02-28 12:00:01']);
        $this->assertFalse($a->fresh()->dormant());
        $a->update(['created_at' => '2020-01-01', 'last_activity_at' => '2023-02-28 12:00:00']);
        $this->assertTrue($a->fresh()->dormant());
        $this->rejects(fn () => $this->move('withdrawal', $a->id, null, 1));
        $this->assertTrue($a->fresh()->dormant());
        $this->move('deposit', null, $a->id, 1);
        $this->assertFalse($a->fresh()->dormant());
    }

    public function test_deletion_is_soft_eligible_only_and_history_survives(): void
    {
        $a = $this->account();
        $this->move('deposit', null, $a->id, 10);
        $this->move('withdrawal', $a->id, null, 10);
        $a->refresh()->update(['last_activity_at' => now()->subYears(2)]);
        app(AccountService::class)->delete($a->id);
        app(AccountService::class)->delete($a->id);
        $this->assertSoftDeleted($a);
        $this->assertSame($a->id, Transaction::first()->destination->id);
        $this->assertSame([], app(AccountReconciliationService::class)->mismatches());
        $recent = $this->account();
        $this->rejects(fn () => app(AccountService::class)->delete($recent->id));
        $funded = $this->account();
        $this->move('deposit', null, $funded->id, 1);
        $funded->refresh()->update(['last_activity_at' => now()->subYears(2)]);
        $this->rejects(fn () => app(AccountService::class)->delete($funded->id));
        $debt = $this->account();
        $this->move('loan_disbursement', null, $debt->id, 1);
        $this->move('withdrawal', $debt->id, null, 1000000);
        $debt->refresh()->update(['last_activity_at' => now()->subYears(2)]);
        $this->rejects(fn () => app(AccountService::class)->delete($debt->id));
    }

    public function test_seed_is_repeatable_and_reconciliation_detects_corruption(): void
    {
        $this->seed();
        $count = Transaction::count();
        $this->seed();
        $this->assertSame($count, Transaction::count());
        $this->artisan('banking:reconcile')->assertExitCode(0);
        BankAccount::first()->increment('balance_minor');
        $this->artisan('banking:reconcile')->assertExitCode(1);
    }

    public function test_guests_and_public_registration_are_blocked(): void
    {
        auth()->logout();
        $this->get('/accounts')->assertRedirect('/login');
        foreach (['/accounts', '/accounts/1/deposits', '/accounts/1/withdrawals', '/accounts/1/loan', '/transfers'] as $url) {
            $this->post($url)->assertRedirect('/login');
        }
        $this->delete('/accounts/1')->assertRedirect('/login');
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_http_demo_sequence_uses_authenticated_routes_and_fixed_loan_amount(): void
    {
        $a = $this->account('HTTP Demo A');
        $b = $this->account('HTTP Demo B');
        $payload = fn (string $amount) => ['amount' => $amount, 'idempotency_key' => (string) Str::uuid()];
        $this->post('/accounts/'.$a->id.'/deposits', $payload('5000'))->assertSessionHasNoErrors();
        $this->post('/accounts/'.$a->id.'/withdrawals', $payload('1000'))->assertSessionHasNoErrors();
        $transfer = [...$payload('1500'), 'source_account_id' => $a->id, 'destination_account_id' => $b->id];
        $this->post('/transfers', $transfer)->assertSessionHasNoErrors();
        $this->post('/transfers', $transfer)->assertSessionHasNoErrors();
        $this->assertSame(250000, $a->fresh()->balance_minor);
        $this->assertSame(150000, $b->fresh()->balance_minor);
        $this->post('/accounts/'.$a->id.'/withdrawals', $payload('5000'))->assertSessionHasErrors('operation');
        // A malicious client-supplied principal cannot change the fixed credit.
        $this->post('/accounts/'.$a->id.'/loan', $payload('99999999'))->assertSessionHasNoErrors();
        $this->assertSame(1250000, $a->fresh()->balance_minor);
        $this->assertSame(1000000, $a->fresh()->loan->outstanding_minor);
        $this->post('/accounts/'.$a->id.'/loan', $payload('10000'))->assertSessionHasErrors('operation');
        $this->assertSame(4, Transaction::count());
        $this->assertSame([], app(AccountReconciliationService::class)->mismatches());
        $empty = $this->account('Dormant HTTP');
        $empty->update(['created_at' => now()->subYears(2)]);
        $this->delete('/accounts/'.$empty->id)->assertRedirect('/accounts');
        $this->assertSoftDeleted($empty);
    }

    public function test_account_and_history_pagination_and_database_timezone(): void
    {
        $a = $this->account();
        for ($i = 0; $i < 11; $i++) {
            $this->account('Pagination '.$i);
            $this->move('deposit', null, $a->id, 1);
        }
        $this->get('/accounts')->assertInertia(fn ($page) => $page->has('accounts.data', 10)->where('accounts.last_page', 2));
        $this->get('/accounts/'.$a->id.'?page=2')->assertInertia(fn ($page) => $page->has('history.data', 1)->where('history.total', 11));
        $this->assertSame('+00:00', DB::selectOne('SELECT @@session.time_zone AS zone')->zone);
    }
}
