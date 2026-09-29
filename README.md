# Kijani Banking Desk

A staff-operated banking assessment built with Laravel, React, TypeScript, Inertia and MySQL. All customers and money are fictional. This is **not production banking software** and the transaction register is **not a complete double-entry ledger**.

## Features

- Create customer accounts with server-generated unique account numbers and zero opening balances.
- Deposit and withdraw KES, including cents, without overdrafts.
- Transfer between accounts atomically with one shared transaction reference.
- Soft-delete eligible dormant accounts while retaining all historical records.
- Disburse a single fixed KES 10,000 demo loan per account, with linked outstanding debt.
- Search and paginate accounts, review transaction history, confirm operations and reconcile balances.

To find deleted accounts, choose **Archived accounts** on the account directory, search by name or account number, then choose **View history**. Archived details show the deletion date and retained transaction history in read-only mode. Deletion does not erase transactions. The seeded Dalia account has no money movements, so its archived history is empty.

## Prerequisites and verified stack

PHP 8.4.20 (64-bit, including pdo_mysql, mbstring, openssl, fileinfo, XML and zip), Composer 2.9.3, Node 22.16.0 and npm 10.9.2 were available on the build machine. The official packaged `laravel/react-starter-kit` v1.0.1 provides the foundation. Locked versions include Laravel 12.69.2, Inertia Laravel 2.0.28, React 19.0.0, Inertia React 2.0.3 and Tailwind 4.0.8. Laravel 12 remains in its security-support period; see the [official support policy](https://laravel.com/docs/12.x/releases).

Use MySQL 8.4 / InnoDB. Initial checks also passed on XAMPP's MariaDB 10.4.32 with the `mysql` driver. That shared XAMPP installation later stopped during startup after reporting a corrupt privilege table. Its data was left untouched; an isolated portable MySQL instance is used for subsequent verification. Consult [reporting.md](reporting.md) for the exact verified engine and current evidence.

On the prepared Windows workspace, MySQL **8.4.11** lives in ignored `.runtime/` and listens only on port **3307**. Local `.env` and `.env.testing` already use that port and database-scoped users. `powershell -File scripts/start-local.ps1` starts this instance if needed and runs the app. The runtime is not committed or required on a fresh clone; use your installed MySQL server and the instructions below. It does not replace or repair XAMPP.

## Install from a fresh checkout

1. Obtain this repository locally, then open a terminal in its root. Public publication is pending authorization; no public clone URL is fabricated.
2. Install locked dependencies:

   ```sh
   composer install --no-interaction --prefer-dist
   npm ci
   ```

3. Copy `.env.example` to `.env` (`Copy-Item .env.example .env` in PowerShell; `cp .env.example .env` on Unix). Keep it untracked.
4. In MySQL, create dedicated databases and users. These example passwords are **local demo values only**. Run as a database administrator, against your intended local instance:

   ```sql
   CREATE DATABASE banking_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE DATABASE banking_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'banking_demo'@'127.0.0.1' IDENTIFIED BY 'local-demo-only';
   GRANT ALL PRIVILEGES ON banking_demo.* TO 'banking_demo'@'127.0.0.1';
   CREATE USER 'banking_test'@'127.0.0.1' IDENTIFIED BY 'local-test-only';
   GRANT ALL PRIVILEGES ON banking_test.* TO 'banking_test'@'127.0.0.1';
   ```

5. Set `.env`: `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, your `DB_PORT` (normally 3306), `DB_DATABASE=banking_demo`, `DB_USERNAME=banking_demo`, `DB_PASSWORD=local-demo-only`. Keep `APP_DEBUG=false`, `APP_TIMEZONE=UTC`, and `APP_URL=http://127.0.0.1:8000`. Never point this demo at a real banking database.
6. Initialize and build:

   ```sh
   php artisan key:generate
   php artisan migrate --seed
   npm run build
   php artisan banking:reconcile
   php artisan serve --host=127.0.0.1 --port=8000
   ```

7. Open [the local application](http://127.0.0.1:8000). Sign in with **staff@example.test** / **DemoBanking!2026**. Public registration is disabled. All authenticated users are staff in this deliberately single-role demo.

For frontend development, run `npm run dev` in a second terminal while Artisan serves the app. The inherited `composer run dev` includes Laravel Pail, which needs Unix process support; on Windows use the separate commands above. PHP's development server is local development tooling, not production hosting. XAMPP Apache must use this repository's `public` directory if configured to serve it; do not expose the repository root.

## Tests and safeguards

Copy `.env.testing.example` to `.env.testing`. Set its database port and test-user credentials and generate a test key with `php artisan key:generate --env=testing`. Test files are ignored; the example is tracked. Do not cache configuration during tests (`php artisan config:clear` clears a prior cache).

```sh
php artisan test
php scripts/concurrency.php
npm run types
npm run lint:check
npm run format:check
php vendor/bin/pint --test
npm run build
php artisan banking:reconcile
```

**Both test commands erase/reset `banking_test`.** Never store needed data there. PHPUnit forces the mysql driver and banking_test name. The application validates the resolved connection database before tests can migrate, including connection-URL overrides and cached configurations. Dedicated test-user permissions provide another boundary. The concurrency script uses independent PHP processes/connections, a ready barrier and brief lock-holding delays. It checks overdraw races, identical keys, cross-account key reuse, opposite transfers, deposit versus deletion, and competing loans. Run it after the ordinary suite, not concurrently with it.

The GitHub workflow provisions MySQL 8.4 and runs the checks. A prepared workflow is not evidence of a successful remote CI run. See reporting.md for actual executions.

## Architecture and rules

React renders server-confirmed data; it never optimistically changes money balances. Form Requests validate/authorize input, controllers orchestrate, and services own rules and transactions. `BankingService` locks each affected account with explicit ascending primary-key lookups inside `DB::transaction`, with three bounded deadlock attempts. Financial writes and loan creation roll back together. Deletion locks the same account row and rechecks eligibility. The named database unique constraint is the final idempotency guard; only a violation of that exact constraint is recovered as a replay after rollback.

- Currency: KES. Signed BIGINT minor units; KES 10,000.00 = 1,000,000 cents. Parsing uses integers and rejects signs, commas, scientific notation, zero and more than two decimal digits. JavaScript formatting is display-only.
- Configurable demo operation and balance cap: KES 100,000,000.00. `BANKING_LIMIT_MINOR=10000000000` is the default in `config/banking.php`. Keep configured limits within PHP integer and JavaScript safe integer ranges. Recipient caps are checked under lock.
- Account numbers: `KE` plus a ULID, protected by a unique index and bounded collision retry. Seed numbers use a deterministic `KE-DEMO-` prefix. Numbers are display identifiers, not authorization secrets.
- Successful movement alone updates activity. Incoming transfers count. Dormant accounts may transact and reactivate.
- Dormancy: activity (or creation when no activity) at or before the current UTC clock minus 12 calendar months using Carbon's no-overflow calculation. UI times use Africa/Nairobi (EAT).
- Deletion: dormant, zero balance and no outstanding debt. Soft deletion keeps FK relationships and history. Repeated deletion is safe.
- One fixed loan ever per account, amount supplied by the server. The transaction precedes the loan record inside the same atomic unit. No interest, repayments or underwriting.
- Exactly one successful transaction row per operation; transfers reference both accounts. Failed operations record no successful row. No transaction edit/delete routes.
- Canonical replay fingerprint: type, actor ID, source ID, destination ID, integer amount. UUID keys are normalized to lowercase. Same key/payload returns the original reference; changed details or actor are rejected. Failed attempts do not consume keys.
- Financial forms keep a key while the mounted form is retried or reopened, and rotate it after confirmed success. Reloading/navigating away discards unsaved form state; after an uncertain response, retry the existing form rather than opening a new operation.
- Reconciliation sums incoming minus outgoing movements, includes soft-deleted accounts, reports mismatches with exit 1, and never repairs balances. Loan principal is not counted twice.

## Fictional seed data and reset

The seeder creates funded active accounts, a recipient, a new zero account, a dormant zero account, a dormant funded account and a loan account. Every nonzero balance has matching history. Repeating `php artisan db:seed` does not duplicate seed accounts or movements or resurrect deleted accounts.

**Destructive local reset:** only after verifying `.env` identifies your disposable `banking_demo` database, run `php artisan migrate:fresh --seed`. This erases all data in that database, including demo staff edits and browser test accounts. Never use it on shared/unknown databases. Ordinary installation uses `migrate --seed`, not a reset.

Demo: create A/B; deposit 5,000 into A; withdraw 1,000; transfer 1,500 to B; verify 2,500/1,500. Reject withdrawal of 5,000 from A; disburse loan to A and verify 12,500 balance with 10,000 debt. Delete the eligible seeded dormant account, then reconcile.

## Scope and handoff

There are no real payments, customer self-service, KYC, multi-currency, complex roles, loan repayments or production security/compliance claims. Staff password recovery uses the local log mailer. This assessment is not internet-ready financial infrastructure. The shared XAMPP database issue is outside the application's schema and was not repaired.

See [the build specification](docs/BUILD_PLAN.md), [progress and defect evidence](reporting.md), and [the candidate recording guide](docs/recording-guide.md). Screenshots, when captured, are in `docs/screenshots/`. Publication, the candidate's personally explained video and application submission remain separate from local code verification.
