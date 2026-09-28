<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoneyOperationRequest;
use App\Services\BankingService;
use App\Services\MoneyParser;

class MoneyOperationController extends Controller
{
    public function __invoke(MoneyOperationRequest $request, BankingService $service, MoneyParser $parser, ?int $account = null)
    {
        $type = match ($request->route()->getName()) {
            'accounts.deposits' => 'deposit', 'accounts.withdrawals' => 'withdrawal',
            'accounts.loan' => 'loan_disbursement', 'transfers.store' => 'transfer',
        };
        $source = $type === 'transfer' ? (int) $request->validated('source_account_id') : ($type === 'withdrawal' ? $account : null);
        $destination = $type === 'transfer' ? (int) $request->validated('destination_account_id') : ($type === 'withdrawal' ? null : $account);
        $minor = $type === 'loan_disbursement' ? 1000000 : $parser->parse($request->validated('amount'));
        $result = $service->execute($type, $request->user()->id, $source, $destination, $minor, $request->validated('idempotency_key'));

        return back()->with('success', ($result['replayed'] ? 'Already processed. Original reference: ' : 'Operation completed. Reference: ').$result['transaction']->reference);
    }
}
