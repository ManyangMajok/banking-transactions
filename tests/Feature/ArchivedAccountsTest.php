<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountService;
use App\Services\BankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArchivedAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_archived_accounts_are_searchable_and_history_remains_readable_but_money_writes_are_rejected(): void
    {
        $this->withoutVite();
        $staff = User::factory()->create();
        $this->actingAs($staff);
        $account = app(AccountService::class)->create('Archived Customer');
        app(AccountService::class)->create('Open Customer');
        $service = app(BankingService::class);
        $service->execute('deposit', $staff->id, null, $account->id, 100, (string) Str::uuid());
        $service->execute('withdrawal', $staff->id, $account->id, null, 100, (string) Str::uuid());
        $account->refresh()->update(['last_activity_at' => now()->subYears(2)]);
        app(AccountService::class)->delete($account->id);

        $this->get('/accounts')->assertInertia(fn ($page) => $page->has('accounts.data', 1)->where('accounts.data.0.customer_name', 'Open Customer'));
        $this->get('/accounts?view=archived&search=Archived')->assertInertia(fn ($page) => $page->where('view', 'archived')->has('accounts.data', 1)->where('accounts.data.0.id', $account->id));
        $this->get('/accounts/'.$account->id)->assertInertia(fn ($page) => $page->where('account.id', $account->id)->where('account.deleted_at', fn ($value) => $value !== null)->has('history.data', 2)->has('recipients', 0));
        foreach (['deposits', 'withdrawals', 'loan'] as $operation) {
            $this->post('/accounts/'.$account->id.'/'.$operation, ['amount' => '1', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('operation');
        }
        $this->assertSame(2, Transaction::count());
        $this->assertSame(0, BankAccount::withTrashed()->findOrFail($account->id)->balance_minor);
        auth()->logout();
        $this->get('/accounts?view=archived')->assertRedirect('/login');
        $this->get('/accounts/'.$account->id)->assertRedirect('/login');
    }
}
