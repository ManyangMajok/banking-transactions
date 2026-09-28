# Banking Project Progress Report

## Current state
- Last updated: 2026-09-28 12:22 UTC
- Current milestone: D08
- Overall state: in_progress
- Branch / latest verified commit: not yet known
- Working tree changes: specification only; no Git repository
- Next action: finish browser verification, documentation and clean installation
- Blocking inputs: none identified

## Environment
| Component | Actual version / evidence |
|---|---|
| PHP / Composer | not yet checked |
| Laravel | not yet checked |
| Node / package manager | not yet checked |
| React / Inertia | not yet checked |
| MySQL / engine | not yet checked |
| Test database isolation | not yet checked |

## Deliverable tracker
Allowed status: not_started, in_progress, blocked, needs_correction, completed.
| ID | Deliverable | Status | Evidence record | Open defect IDs |
|---|---|---|---|---|
| D00 | Audit and reporting | completed | — | — |
| D01 | Foundation and authentication | not_started | — | — |
| D02 | Schema, money and seed data | not_started | — | — |
| D03 | Account creation and views | not_started | — | — |
| D04 | Deposits and withdrawals | not_started | — | — |
| D05 | Transfers | not_started | — | — |
| D06 | Dormancy and deletion | not_started | — | — |
| D07 | Loan disbursement | not_started | — | — |
| D08 | UI and verification | in_progress | — | — |
| D09 | Documentation and clean clone | not_started | — | — |
| D10 | Publication and handoff | not_started | — | — |

## Deliverable evidence log
### [Timestamp] Dxx — title — status
- Objective and acceptance criteria:
- Implemented behaviour:
- Files added/changed:
- Commands actually executed and exit codes:
- Test counts/results and relevant output:
- Manual/browser checks and screenshot paths:
- Deviations and rationale:
- Defects found or corrected:
- Commit containing implementation, if created:
- Remaining work and next action:

## Verification matrix
Track T01–T17 and each concurrency scenario from the build plan.
| Check | Status (not_run/pass/fail/blocked) | Evidence | Last verified revision |
|---|---|---|---|
| T01 | not_run | — | — |

## Defect log
### BUG-001 — title
- Severity and state:
- Affected deliverables:
- Reproduction steps:
- Expected / actual:
- Root cause:
- Fix and changed files:
- Regression check:
- Retest command and result:
- Closed at / verified revision:

## Decisions and assumptions
| ID | Decision | Reason | Impact / approval needed |
|---|---|---|---|
| DEC-001 | Use documented demo business rules | Bound assessment scope | Record any subsequent change |

## Final handoff checklist
- [ ] Required five operations verified
- [ ] Bonus loan verified
- [ ] Rollback, idempotency and concurrency evidence recorded
- [ ] MySQL reconciliation passes
- [ ] Responsive UI and keyboard flows inspected
- [ ] Build/type/lint/test checks pass
- [ ] Fresh-clone instructions verified
- [ ] No open critical/high defects
- [ ] No secrets or real customer data committed
- [ ] Public GitHub publication authorized and anonymous access verified
- [ ] Exact repository URL recorded
- [ ] Candidate recording reviewed and below 100 MB
- [ ] Remaining limitations explicitly disclosed

## Submission state
- Code: not_started
- Repository: not_published
- Repository URL: not yet available
- Video: not_recorded
- Application submission: not_submitted


### 2026-09-28 D00 — audit — completed
- Existing folder contained only the build specification; no Git repository or existing application.
- PHP 8.4.20 with pdo_mysql, Composer 2.9.3, Node 22.16.0, npm 10.9.2.
- XAMPP exposes MariaDB 10.4.32 / InnoDB, not Oracle MySQL. Existing databases were listed read-only; none will be modified.
- Official packaged laravel/react-starter-kit v1.0.1 uses Laravel 12, React 19, Inertia 2 and Tailwind 4. Use its supported version line and lock resolved dependencies.
- Initial Composer download failed because sandbox networking was restricted; escalated retry succeeded (exit 0). No application defect.
- Applying baseline-ui and fixing-accessibility skills to financial forms.

### 2026-09-28 D01 — foundation — in_progress
- Official starter copied into repository root. Installing dependencies, configuring dedicated databases and staff authentication.
- Environment deviation: use the user's active XAMPP MariaDB with Laravel's mysql driver; report actual engine explicitly. Oracle MySQL execution remains separate from MariaDB evidence.

### D01 foundation / D02 schema — implementation and initial checks
- `composer install --no-interaction --prefer-dist`: exit 0, Laravel 12.69.2, inertia-laravel 2.0.28; lockfile saved.
- `npm install`: exit 0, reported 23 vulnerabilities; patch remediation started before accepting foundation.
- `php artisan migrate --no-interaction`: exit 0; all four migrations applied to newly created banking_demo.
- Created banking_demo, banking_test and banking_fresh. XAMPP failed GRANT with ERROR 1034: mysql.db privilege table corrupt. Existing root local connection works; no shared table repairs attempted. Dedicated database name guard added for all testing boots.
- D02 in_progress: signed BIGINT schema, shape checks, foreign keys, integer parser, soft-delete models, fictional coherent seed data and reconciliation.
- D03–D07 service code staged in dependency order; acceptance not claimed until domain tests and UI pass. Account creation uses ULID plus unique constraint retry. Shared financial service uses sorted account locks, three deadlock attempts and exact constraint-specific replay recovery.
- Files: config/banking.php, database/migrations/*banking*, app/Models/{BankAccount,Transaction,Loan}.php, app/Services/*.php, app/Http/Requests/*, app/Http/Controllers/{BankAccountController,MoneyOperationController}.php, routes/web.php.


### 2026-09-28 D02–D07 — domain acceptance on XAMPP
- `php artisan test --filter=BankingTest`: 13 tests, 116 assertions passed, exit 0.
- `php scripts/concurrency.php`: six scenarios passed using independent PHP processes, a ready barrier and 200 ms pauses while locks are held. Tested competing withdrawals, identical keys, keys reused across distinct accounts, opposite transfers, deposit vs deletion and competing loans. Final reconciliation passed on MariaDB 10.4.32 / InnoDB. No Oracle MySQL execution claimed.
- `php artisan db:seed --no-interaction` and `php artisan banking:reconcile`: exit 0, zero mismatches.
- `npm run build`: passed outside sandbox after esbuild parent-directory access restriction; frontend build generated.
- `npx tsc --noEmit`, `npm run lint`, `npm run format`: exit 0 after starter compatibility fixes.
- `npm audit fix`: final result zero vulnerabilities.
- `php vendor/bin/pint`: exit 0; formatted PHP.
- Files: tests/Feature/BankingTest.php, scripts/concurrency.php, resources/js/pages/banking/*.tsx and financial services.
- Acceptance remaining: browser all-feature demonstration, mobile/keyboard inspection; D08 remains in progress.

### BUG-001 — starter dependency vulnerabilities — closed
- Severity: high. Initial npm install reported 23 findings including two critical.
- Cause: old starter lock resolutions/transitive packages. Ran npm audit fix; final audit reports zero vulnerabilities. Manifests and package-lock.json updated.

### BUG-002 — starter TypeScript compatibility — closed
- Severity: medium. tsc rejected three form interfaces against updated Inertia constraints and obsolete CSS blend value.
- Fix: form type aliases and valid multiply blend value; tsc exit 0. Financial code did not need weakened typing.

### BUG-003 — HTTP test UUID fixtures — closed
- Severity: test defect. Initial suite 36 passed / 2 failed. Fixtures passed UUID objects, unlike browser string payloads.
- Fix: explicitly cast HTTP fixture keys to strings. Banking suite rerun: 13 passed / 116 assertions. Validation unchanged.

### Environment issue — XAMPP privilege table
- ERROR 1034 on GRANT; mysql.db index corruption pre-existed app migrations. Dedicated databases created successfully; no shared table repairs performed.
- Application uses existing local root connection; test bootstrap refuses non-mysql driver or any database name other than banking_test. Document least-privilege setup for clean environments.

### 2026-09-28 17:01 UTC D01–D07 — MySQL acceptance
- XAMPP stopped between work sessions; read-only connection attempts failed with 10061. Normal restart exited during startup. No repair/deletion of shared XAMPP data was performed.
- Downloaded official portable MySQL 8.4.11 from cdn.mysql.com into ignored .runtime; initialized a new data directory; listening only on 127.0.0.1:3307 with X protocol disabled. Created database-scoped demo/test/fresh users. Existing XAMPP databases remain separate.
- `php artisan migrate --seed --no-interaction`: exit 0 on MySQL 8.4.11.
- `php artisan test`: 38 passed / 180 assertions, exit 0 on MySQL 8.4.11.
- `php artisan banking:reconcile`: zero mismatches, exit 0.
- Browser: staff login, creation of Demo Account A (zero), empty history, deposit 5,000, balance/history/reference refresh verified.
- Automatic approval review rejected simulated withdrawal in browser; user approval requested. Remaining browser mutations pending; existing automated feature evidence is unaffected.
- D09 in_progress: README, recording guide and MySQL CI workflow written; fresh-clone acceptance still pending.

### BUG-004 — dialog style edit syntax — closed
- Severity: medium, discovered by TypeScript before release. An overly broad class-removal expression removed a closing quote.
- Replaced dialog component styling explicitly while retaining Radix primitives. `npm run types` exit 0 and `npm run build` exit 0 after correction. No financial code affected.
