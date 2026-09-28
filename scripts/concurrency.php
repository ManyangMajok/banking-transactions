<?php

// Real independent-process tests. This script ONLY resets banking_test.
use App\Models\BankAccount;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountReconciliationService;
use App\Services\AccountService;
use App\Services\BankingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'banking_test'] as $name => $value) {
    putenv("$name=$value");
    $_ENV[$name] = $_SERVER[$name] = $value;
}
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::connection()->getDatabaseName() !== 'banking_test' || DB::getDriverName() !== 'mysql') {
    throw new RuntimeException('Unsafe database.');
}

if (($argv[1] ?? '') === 'worker') {
    $job = json_decode(base64_decode($argv[2]), true, flags: JSON_THROW_ON_ERROR);
    file_put_contents($job['ready'], 'ready');
    $deadline = microtime(true) + 15;
    while (! file_exists($job['gate'])) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Barrier timeout');
        } usleep(10000);
    }
    try {
        if ($job['type'] === 'delete') {
            app(AccountService::class)->delete($job['source']);
            $replayed = false;
        } else {
            $service = new class extends BankingService
            {
                protected function checkpoint(string $stage): void
                {
                    usleep(200000);
                }
            };
            $result = $service->execute($job['type'], $job['actor'], $job['source'], $job['destination'], $job['minor'], $job['key']);
            $replayed = $result['replayed'];
        }
        echo json_encode(['ok' => true, 'replayed' => $replayed]);
    } catch (ValidationException $e) {
        echo json_encode(['ok' => false, 'errors' => $e->errors()]);
    }
    exit(0);
}

function verify(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
function pair(array $first, array $second): array
{
    $gate = storage_path('framework/testing-'.Str::uuid());
    $processes = [];
    try {
        foreach ([$first, $second] as $i => $job) {
            $job['gate'] = $gate;
            $job['ready'] = $gate.'.'.$i;
            $p = new Process([PHP_BINARY, __FILE__, 'worker', base64_encode(json_encode($job))], dirname(__DIR__));
            $p->setTimeout(25);
            $p->start();
            $processes[] = $p;
        }
        $deadline = microtime(true) + 15;
        while (! file_exists($gate.'.0') || ! file_exists($gate.'.1')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Workers did not become ready');
            } usleep(10000);
        }
        file_put_contents($gate, 'go');
        $results = [];
        foreach ($processes as $p) {
            $p->wait();
            verify($p->isSuccessful(), $p->getErrorOutput());
            $results[] = json_decode($p->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    } finally {
        foreach ($processes as $p) {
            if ($p->isRunning()) {
                $p->stop();
            }
        }
        foreach ([$gate, $gate.'.0', $gate.'.1'] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}

Artisan::call('migrate:fresh', ['--force' => true]);
$staff = User::factory()->create();
$accounts = app(AccountService::class);
$service = app(BankingService::class);
$job = fn ($type, $source, $destination, $minor, $key = null) => ['type' => $type, 'actor' => $staff->id, 'source' => $source, 'destination' => $destination, 'minor' => $minor, 'key' => $key ?? (string) Str::uuid()];
$deposit = fn ($account, $minor) => $service->execute('deposit', $staff->id, null, $account->id, $minor, Str::uuid());

$a = $accounts->create('Concurrent withdrawal');
$deposit($a, 10000);
$r = pair($job('withdrawal', $a->id, null, 7500), $job('withdrawal', $a->id, null, 7500));
verify(count(array_filter($r, fn ($r) => $r['ok'])) === 1 && $a->fresh()->balance_minor === 2500, 'Withdrawal race failed');
echo "PASS competing withdrawals: one success, balance 2500\n";

$a = $accounts->create('Same key');
$key = (string) Str::uuid();
$count = Transaction::count();
$r = pair($job('deposit', null, $a->id, 100, $key), $job('deposit', null, $a->id, 100, $key));
verify($r[0]['ok'] && $r[1]['ok'] && $a->fresh()->balance_minor === 100 && Transaction::count() === $count + 1, 'Identical key race failed');
echo "PASS identical key: one movement, both responses successful\n";

$a = $accounts->create('Cross-key A');
$b = $accounts->create('Cross-key B');
$key = (string) Str::uuid();
$count = Transaction::count();
$r = pair($job('deposit', null, $a->id, 100, $key), $job('deposit', null, $b->id, 100, $key));
verify(count(array_filter($r, fn ($r) => $r['ok'])) === 1 && $a->fresh()->balance_minor + $b->fresh()->balance_minor === 100 && Transaction::count() === $count + 1, 'Cross-account key race failed');
echo "PASS cross-account key collision: one credit, changed payload rejected\n";

$a = $accounts->create('Opposite A');
$b = $accounts->create('Opposite B');
$deposit($a, 10000);
$deposit($b, 10000);
$r = pair($job('transfer', $a->id, $b->id, 1000), $job('transfer', $b->id, $a->id, 2000));
verify($r[0]['ok'] && $r[1]['ok'] && $a->fresh()->balance_minor === 11000 && $b->fresh()->balance_minor === 9000, 'Opposite transfer race failed');
echo "PASS opposite transfers: both complete, conserved 20000\n";

$a = $accounts->create('Delete race');
$a->update(['created_at' => now()->subYears(2)]);
$r = pair($job('deposit', null, $a->id, 100), $job('delete', $a->id, null, 0));
$after = BankAccount::withTrashed()->findOrFail($a->id);
verify(count(array_filter($r, fn ($r) => $r['ok'])) === 1 && ($after->trashed() ? $after->balance_minor === 0 : $after->balance_minor === 100), 'Deletion race failed');
echo "PASS deposit vs delete: serialized eligible outcome\n";

$a = $accounts->create('Loan race');
$count = Transaction::count();
$r = pair($job('loan_disbursement', null, $a->id, 1000000), $job('loan_disbursement', null, $a->id, 1000000));
verify(count(array_filter($r, fn ($r) => $r['ok'])) === 1 && $a->fresh()->balance_minor === 1000000 && Loan::where('bank_account_id', $a->id)->count() === 1 && Transaction::count() === $count + 1, 'Loan race failed');
echo "PASS concurrent loans: one loan, one credit\n";
verify(app(AccountReconciliationService::class)->mismatches() === [], 'Reconciliation failed');
echo 'PASS reconciliation; engine '.DB::selectOne('SELECT VERSION() AS version')->version."\n";
