<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBankAccountRequest;
use App\Models\BankAccount;
use App\Models\Transaction;
use App\Services\AccountService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BankAccountController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['search' => ['nullable', 'string', 'max:120'], 'view' => ['nullable', 'in:open,archived']]);
        $view = $request->input('view', 'open');
        $search = $request->string('search')->trim()->toString();
        $accounts = BankAccount::with('loan')->when($view === 'archived', fn ($q) => $q->onlyTrashed())->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('customer_name', 'like', '%'.$search.'%')->orWhere('account_number', 'like', '%'.$search.'%')))
            ->latest('id')->paginate(10)->withQueryString()->through(fn ($a) => $a->display());

        return Inertia::render('banking/accounts', ['accounts' => $accounts, 'search' => $search, 'view' => $view,
            'summary' => ['count' => BankAccount::count(), 'balance_minor' => (int) BankAccount::sum('balance_minor')]]);
    }

    public function store(StoreBankAccountRequest $request, AccountService $service)
    {
        $account = $service->create($request->validated('customer_name'));

        return to_route('accounts.show', $account)->with('success', 'Account '.$account->account_number.' created with a zero balance.');
    }

    public function show(BankAccount $account)
    {
        $account->load('loan');
        $history = Transaction::with(['source:id,customer_name,account_number,deleted_at', 'destination:id,customer_name,account_number,deleted_at'])
            ->where(fn ($q) => $q->where('source_account_id', $account->id)->orWhere('destination_account_id', $account->id))
            ->latest('id')->paginate(10);

        return Inertia::render('banking/detail', ['account' => $account->display(), 'loan' => $account->loan,
            'history' => $history, 'recipients' => $account->trashed() ? [] : BankAccount::whereKeyNot($account->id)->orderBy('customer_name')->get(['id', 'customer_name', 'account_number'])]);
    }

    public function destroy(int $account, AccountService $service)
    {
        $service->delete($account);

        return to_route('accounts.index')->with('success', 'Dormant account deleted. Its records have been retained.');
    }
}
