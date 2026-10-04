# Banking Project Progress Report

## Current state
- Last updated: 2026-10-04 (Africa/Nairobi)
- Current milestone: D10 — authorized GitHub publication and presentation handoff
- Overall state: code verified locally; publication in progress; candidate recording pending
- Branch: main
- Working tree changes: professional branding and presentation documentation prepared for publication
- Next action: push to the user-authorized public repository and verify access
- Blocking inputs: none for publication; the candidate must record and submit their own video
## Environment
| Component | Actual version / evidence |
|---|---|
| PHP / Composer | PHP 8.4.20 / Composer 2.9.3 |
| Laravel | 12.69.2 |
| Node / package manager | Node 22.16.0 / npm 10.9.2 |
| React / Inertia | React 19.0.0 / Inertia React 2.0.3 / inertia-laravel 2.0.28 |
| MySQL / engine | MySQL 8.4.11 / InnoDB; isolated local port 3307 |
| Test database isolation | banking_test enforced by application guard and separate database user |

## Deliverable tracker
Allowed status: not_started, in_progress, blocked, needs_correction, completed.
| ID | Deliverable | Status | Evidence record | Open defect IDs |
|---|---|---|---|---|
| D00 | Audit and reporting | completed | — | — |
| D01 | Foundation and authentication | completed | MySQL acceptance and 2026-10-04 full-suite verification | — |
| D02 | Schema, money and seed data | completed | MySQL acceptance and 2026-10-04 full-suite verification | — |
| D03 | Account creation and views | completed | MySQL acceptance and 2026-10-04 full-suite verification | — |
| D04 | Deposits and withdrawals | completed | MySQL acceptance and 2026-10-04 full-suite verification | — |
| D05 | Transfers | completed | MySQL acceptance and 2026-10-04 full-suite verification | — |
| D06 | Dormancy and deletion | completed | MySQL acceptance and 2026-10-04 full-suite verification | — |
| D07 | Loan disbursement | completed | MySQL acceptance and 2026-10-04 full-suite verification | — |
| D08 | UI and verification | completed | Prior UI inspection, user-confirmed 12 flows, 2026-10-04 automated checks | — |
| D09 | Documentation and clean clone | in_progress | Installation and migration evidence recorded; full clean-clone browser acceptance remains unconfirmed | — |
| D10 | Publication and handoff | in_progress | Publication authorized 2026-10-04; personal video pending | — |

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
- Code: implemented and locally verified
- Repository: publication authorized and in progress
- Repository URL: https://github.com/ManyangMajok/banking-transactions
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

### 2026-09-28 D08 — expanded verification
- MySQL concurrency rerun: all six scenarios and final reconciliation passed, exit 0, engine 8.4.11.
- Added HTTP full-sequence and pagination tests; `php artisan test --filter=BankingTest`: 15 tests / 159 assertions passed.
- Database sessions now explicitly set UTC and REPEATABLE READ; test asserts session timezone. Initial generated demonstration records predate this connection correction; fresh installations initialize correctly in UTC.
- Browser checks: 375px account detail (scrollWidth=375), mobile directory, empty-search state, Radix focus wrap (Shift+Tab remains inside dialog), Escape closes and restores Deposit trigger. Tokenless browser POST returned 419 Page Expired; temporary fixture removed.
- Read-only inspection confirmed seeded loan principal, outstanding amount, linked history reference and disbursement date render.
- Screenshot files: docs/screenshots/account-detail.png, account-mobile.png, accounts-mobile.png, loan-account.png, csrf-rejection.png. Full-page capture can show stitching artifacts; viewport captures are preferred for final delivery.
- Local commit f147969 contains implementation. Managed clean-install worktree failed because sandbox ownership differed; created a separate local clone at .runtime/fresh-clone using command-scoped safe.directory exceptions. No global Git settings altered.
- `npm ci` in fresh clone: exit 0, 438 packages, audit zero vulnerabilities. Composer clean installation in progress.

### 2026-09-29 — user acceptance and archived-history correction
- User reports all 12 manual browser checks passed, including deleting Dalia. This is user-reported evidence, not agent-operated browser execution.
- BUG-005 (high, in_progress): deleted account rows/history are preserved, but default route binding and directory scope prevent staff opening deleted-account history. Add searchable Archived accounts directory and read-only detail, retaining all write restrictions. No development data reset.

### 2026-09-29 BUG-005 — archived history access — closed
- Added Open accounts / Archived accounts navigation, archive-scoped name/number search and pagination, and View history links.
- GET account detail now includes soft-deleted accounts for authenticated staff. Archived details show deletion timestamp and retained history; deposit/withdraw/transfer/loan/delete controls are absent. Existing service locks and deleted-account rejection remain intact.
- Files: routes/web.php; app/Http/Controllers/BankAccountController.php; app/Models/BankAccount.php; resources/js/pages/banking/{accounts,detail,shared}.tsx; tests/Feature/ArchivedAccountsTest.php; README.md.
- `php artisan test --filter=ArchivedAccountsTest`: 1 passed, 54 assertions. Exercises funded-then-drained archived history, list filtering/search, access control and rejection of archived financial writes.
- `php vendor/bin/pint`: passed; `npm run types`: passed; `npm run build`: passed (2035 modules). No development reset or customer movement performed.
- Read-only live check: Dalia id=4, deleted_at=2026-09-29T08:12:39Z, balance=0, transaction count=0. Her seeded account never had money movements. `php artisan banking:reconcile`: zero mismatches including archived accounts.
- User-reported completion of all 12 manual checks retained above; archived-history UI is new in this correction.

### 2026-09-29 — professional interface wording — in_progress
- User requested removal of demo/simulation presentation. Update screen copy, loan-account terminology, validation messages and starter branding. Preserve financial behavior, existing account identifiers, credentials and historical records; technical scope documentation remains accurate.
- Completed presentation changes: banking terminology, linked loan-account wording, validation messages, staff display label, starter navigation/logo replacement and banking browser icon. Existing identifiers, credentials and financial records preserved. Technical documentation remains accurate.
- Validation: frontend build, TypeScript, ESLint and Pint passed. Source scan found no demo/simulated/fictional wording in resources/js or app/Services. Balance reconciliation after the staff display-name update reported zero mismatches.

### 2026-10-04 — presentation startup and publication — in_progress
- User explicitly authorized starting the app and pushing to https://github.com/ManyangMajok/banking-transactions.git. GitHub CLI confirmed that ManyangMajok is the active authenticated account; target repository is public and has no branches.
- Started scripts/start-local.ps1 in a hidden background process. GET http://127.0.0.1:8000/login returned HTTP 200. The compiled frontend is served with Laravel; a separate Vite server is unnecessary for presenting.
- `php artisan test`: exit 0, 41 tests / 277 assertions passed on MySQL 8.4.11. `php scripts/concurrency.php`: exit 0, all six scenarios and reconciliation passed. `php artisan banking:reconcile`: exit 0, zero mismatches.
- Created a separate local fixture named Presentation Dormant Account, zero balance, creation timestamp 13 months ago, no debt and no transactions, to demonstrate eligible deletion. The original archived Dalia account and other records were preserved. This is prepared test data; the walkthrough explicitly discloses the timestamp setup.
- Added docs/presentation-walkthrough.md with exact operations, expected balances, client-facing explanations and recording caveats. Updated README clone URL and bonus-feature wording. Corrected the recording guide: the employer's supplied instructions do not impose a duration or 100 MB file-size limit.
- Updated this report's stale summary and deliverable tracker; historical evidence entries remain preserved. Clean-clone browser acceptance and the candidate's video remain distinct unfinished items.
- Publication review: only example environment files occur in Git history; actual .env files, credentials in runtime files, MySQL data, dependencies and generated builds are ignored. Tracked local example passwords are documented assessment defaults, not personal credentials. Previously captured screenshots use fictional assessment records. The unrelated untracked accounts-desktop.png is not included in this publication commit.