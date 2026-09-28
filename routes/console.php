<?php

use App\Services\AccountReconciliationService;
use Illuminate\Support\Facades\Artisan;

Artisan::command('banking:reconcile', function (AccountReconciliationService $service) {
    $mismatches = $service->mismatches();
    foreach ($mismatches as $row) {
        $this->error(json_encode($row));
    }
    $this->info(count($mismatches).' reconciliation mismatches (including deleted accounts).');

    return count($mismatches) ? 1 : 0;
})->purpose('Read-only comparison of stored balances and recorded movements');
