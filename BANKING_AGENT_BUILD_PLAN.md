# Banking Transaction Interface — Agent Build Specification

## 1. Mission and execution contract

Build a working assessment project using **Laravel, React, TypeScript, Inertia and MySQL**. Deliver clear code, a usable interface, verified financial operations, installation documentation and truthful progress reporting.

The employer requires account creation, deposits, withdrawals, account-to-account transfers, deletion of dormant accounts, and a bonus loan account that disburses **KES 10,000** into its linked bank account. The submission requires a public GitHub URL and a video explaining the candidate's implementation decisions.

This document specifies a staff-operated demonstration with fictional data and simulated money. Do not represent it as production banking software or integrate real payments.

### Instructions to the implementing agent

1. Read this entire specification and any applicable repository instructions before editing.
2. Inspect the existing repository, runtime and Git state. Preserve existing work. Do not overwrite unrelated changes.
3. Create **`reporting.md` at the repository root before implementation** using the template in section 15. If it exists, read and update it; never reset it.
4. Implement milestones D00–D10 in dependency order. Work in small, reviewable increments.
5. Update `reporting.md` when starting and finishing each deliverable, when a check fails, when correcting a defect, and before handing off or stopping.
6. Each completed deliverable needs acceptance evidence, changed file paths, actual commands and results. Never claim a test was run if it was not.
7. Investigate failed checks, record the cause, implement a correction and rerun the relevant verification. Never weaken a test merely to obtain a passing result.
8. Continue routine reversible implementation autonomously. Record reasonable decisions. Ask only when a genuine missing requirement, credential or access decision blocks progress.
9. Do not publish a repository, deploy the app, submit the application or send messages without the user's authorization. Prepare everything locally while external publication is pending.
10. Do not fabricate video recordings, commits, screenshots, test evidence or client explanations. Help the candidate understand and explain the actual implementation.
11. No multi-agent delegation is required. If separately authorized, use clear file ownership and one reporting coordinator.

### Definition of done

All required features and the bonus work through React and Laravel against MySQL; critical tests pass; balances reconcile with recorded movements; duplicate submissions cannot move money twice; the interface is inspected; a fresh clone can be installed; `reporting.md` accurately describes completion, defects and limitations. External publication and the candidate's recording must be reported separately from code completion.

## 2. Architecture and scope

Use a single Laravel repository with the official React/Inertia starter kit, React with TypeScript, Tailwind CSS and the kit's component library. Verify supported PHP, Node, Laravel and MySQL versions at implementation time and record exact installed versions. Commit `composer.lock` and the package-manager lockfile. Use the starter kit's existing scripts rather than assuming script names.

Responsibilities:

| Layer | Responsibility |
|---|---|
| React pages/components | Forms, errors, confirmation, server-provided data and formatting |
| Inertia + Laravel web routes | Page delivery, submissions, redirects and session feedback |
| Form Requests | Input shape, size and format validation; request authorization |
| Controllers | Thin orchestration; invoke services and return responses |
| Services | Transaction boundaries, locking, eligibility and business rules |
| Models | Relationships, casts and small state helpers |
| MySQL/InnoDB | Durable records, unique constraints, foreign keys and row locks |

Use session authentication and CSRF protection from the starter kit. Seed a local demo staff user. Disable public registration. All authenticated users are staff in this deliberately single-role application; document this limitation. Do not silently allow unauthenticated writes.

Out of scope: customer self-service, real transfers, card details, KYC, multi-currency, interest, repayment schedules, loan underwriting, notifications, complex roles and a full accounting ledger. Hosting is optional; the public repository and video are required for submission.

## 3. Business rules and invariants

| ID | Rule |
|---|---|
| BR01 | Currency is always KES; opening balance is zero. |
| BR02 | Amounts are positive decimal strings with at most two fractional digits; reject scientific notation, signs, commas and malformed values. |
| BR03 | Persist money as signed BIGINT minor units: KES 10,000.00 = 1,000,000 cents. No floating-point arithmetic for financial calculations. |
| BR04 | Demo maximum single operation and account balance: KES 100,000,000.00 (10,000,000,000 cents). Enforce server-side, including recipient overflow checks. Document as a configurable demo limit. |
| BR05 | No overdrafts; a withdrawal equal to the available balance is allowed. |
| BR06 | Transfers require two distinct, existing, non-deleted accounts. Total money across those two accounts does not change. |
| BR07 | Dormant means activity date <= the clock's current time minus 12 calendar months, using a no-overflow calendar calculation. Use last_activity_at, or created_at if null. Store timestamps in UTC. |
| BR08 | Only successful money movement updates activity; viewing, editing names and failed requests do not. Incoming transfers count as activity. |
| BR09 | Demo dormant accounts may transact; a successful movement reactivates them. Document this assumption. |
| BR10 | Delete only a dormant account with zero balance and no outstanding loan. Soft-delete it and preserve all records. |
| BR11 | Each bank account can receive only one demo loan ever. Principal is fixed server-side at KES 10,000. No repayment interface is included. |
| BR12 | Loan creation, account credit and transaction history must succeed or roll back together. |
| BR13 | A successful operation writes exactly one transaction row. A transfer has one row referencing both accounts. Failed operations write no successful transaction record. |
| BR14 | Every submitted money operation has an idempotency key. Repeating the same key and canonical payload returns the original result; changing its payload is rejected. |
| BR15 | Balances cannot be set through generic account-update requests or trusted from React. |
| BR16 | Transaction history is immutable through the application. Do not add transaction edit/delete actions. |

The demo's transaction register is not a complete double-entry ledger. Say so explicitly in the README.

## 4. Data design

Use migrations, foreign keys and explicit indexes. Use MySQL/InnoDB in development and financial integration tests. Do not switch to SQLite and claim row-locking behaviour is verified.

### users

Use the starter-kit users table for staff identities. Do not conflate a staff login with a customer's bank account.

### bank_accounts

| Column | Type/constraint |
|---|---|
| id | Primary key |
| account_number | String, unique, immutable, generated on server |
| customer_name | Required string, max 120 characters |
| balance_minor | BIGINT, default 0, nonnegative constraint where supported |
| last_activity_at | Nullable timestamp, indexed |
| created_at / updated_at | Timestamps |
| deleted_at | Nullable timestamp for soft deletion |

Account numbers are display identifiers, never authorization credentials. Use a documented unique generation scheme and handle collisions using the database constraint. Do not use a race-prone `max(id) + 1` scheme.

### transactions

| Column | Type/constraint |
|---|---|
| id | Primary key |
| reference | Unique server-generated UUID/ULID or equivalent |
| type | Validated enum/value: deposit, withdrawal, transfer, loan_disbursement |
| source_account_id | Nullable FK to bank_accounts, restrict physical deletion |
| destination_account_id | Nullable FK to bank_accounts, restrict physical deletion |
| amount_minor | BIGINT, strictly positive |
| performed_by | Required FK to staff users |
| idempotency_key | Required UUID string, unique |
| request_hash | Canonical payload fingerprint for replay checking |
| created_at | Timestamp |

Index source_account_id + created_at and destination_account_id + created_at. Deposit/loan: destination only. Withdrawal: source only. Transfer: both distinct. Validate these shapes in services and add database checks when supported. Preserve relations to soft-deleted accounts when showing history.

Canonical request fingerprint: operation type, actor ID, source ID, destination ID and integer amount. Do not hash arbitrary JSON serialization or client-supplied balances. Invalid attempts do not consume a key. A successful retry uses the original committed transaction.

### loans

| Column | Type/constraint |
|---|---|
| id | Primary key |
| loan_number | Unique server-generated display identifier |
| bank_account_id | Unique FK: one demo loan per bank account |
| principal_minor | BIGINT, 1,000,000 |
| outstanding_minor | BIGINT, initially equal to principal |
| disbursement_transaction_id | Unique FK to transactions |
| disbursed_at | Timestamp |
| created_at / updated_at | Timestamps |

Create the loan-disbursement transaction before the linked loan row within the same database transaction. If any later write fails, all earlier writes must roll back.

### Reconciliation

Implement a read-only reconciliation command or service: for each account, sum incoming transaction amounts minus outgoing amounts and compare to balance_minor. Include soft-deleted accounts. Loans count through their disbursement transactions only; do not add principal twice. Return a nonzero exit code for mismatches and never silently repair balances.

## 5. Safe money conversion and concurrency

### Money conversion

Use a tested helper accepting a trimmed decimal string. Validate maximum length and format first. Split whole/fractional parts, right-pad the fraction to two digits and calculate cents using integers. Enforce operation limits before persistence. Reject invalid precision rather than silently rounding. Test 0.01, 1, 1.2, 1.20, malformed values and boundary limits.

Return raw minor-unit values and/or server-formatted values consistently. The configured cap fits within JavaScript's safe integer range. Use locale-aware KES formatting for display only; never parse formatted text to update balances.

### Transaction and lock protocol

All financial writes and deletion eligibility checks occur inside `DB::transaction()`:

1. Form Request validates input shape. Service rechecks mutable state under lock.
2. For each affected account, acquire `lockForUpdate()` in ascending account ID order. Use explicit sorted primary-key lookups for predictable ordering.
3. Re-fetch eligibility and balance from these locked rows, not route-bound objects loaded earlier.
4. Check for an already committed idempotency key and compare the canonical request hash.
5. Validate sufficient balance, recipient cap, deletion status and applicable loan rules.
6. Update balances, write history and update activity in the same transaction.
7. Commit before returning success. Do not perform external calls within the transaction.

Use the same lock ordering for every path. Add a bounded deadlock retry policy supported by the installed Laravel version and document it. Keep retry closures deterministic with no external side effects.

Enforce idempotency with the database unique key as the final guard. Concurrent requests touching different accounts may still reuse a key: catch the specific idempotency uniqueness violation **outside the rolled-back transaction**, load the committed original and compare hashes. Do not interpret every integrity error as a duplicate submission. A replay should return the original reference, not create history again.

Financial forms generate one UUID per intended operation. Retain it on network uncertainty/retry; generate a new key after confirmed success or a deliberate new operation. Disabling the button is useful UX but not duplicate protection.

For Inertia, redirect successful submissions with a flash message and reference; use field/form errors for rejected business actions. A replay is successful but should explain it was already processed. Avoid sending raw database exceptions to the interface.

## 6. Backend implementation contract

Suggested classes (adapt to existing conventions without unnecessary abstractions):

- Models: BankAccount, Transaction, Loan.
- Requests: StoreBankAccountRequest, DepositRequest, WithdrawalRequest, TransferRequest, DisburseLoanRequest.
- Services: AccountService, BankingService, MoneyParser, AccountReconciliationService.
- Controllers: BankAccountController, DepositController, WithdrawalController, TransferController, LoanController.
- AccountResource or equivalent: explicit safe properties for React.
- Shared clock/date helper for testable dormancy.

| Method | Route | Behaviour |
|---|---|---|
| GET | /accounts | Paginated searchable list, exclude deleted accounts |
| GET | /accounts/{account} | Details, paginated history and loan |
| POST | /accounts | Create zero-balance account |
| POST | /accounts/{account}/deposits | Deposit amount |
| POST | /accounts/{account}/withdrawals | Withdraw amount |
| POST | /transfers | Transfer source, destination, amount |
| DELETE | /accounts/{account} | Soft-delete only when eligible |
| POST | /accounts/{account}/loan | Create and disburse fixed loan |

Apply authentication and CSRF protection to every write route. Validate account lookup server-side. Escape user text through normal React rendering; do not use raw HTML for names. Whitelist writable fields. Paginate account history and use eager loading to avoid repeated queries.

Account deletion: recheck dormancy, zero balance and outstanding debt while holding the account lock. Competing deposits/loans must use that same lock. A repeated delete must not corrupt history. No hard-delete route.

Loan: lock account, perform replay check, reject a different new request if a loan already exists, enforce recipient cap, credit 1,000,000 cents, write the disbursement and loan atomically. Do not trust a client amount.

## 7. React and interface design

Design a polished but restrained desktop-first dashboard that also works at 375px mobile width. Use readable type, neutral surfaces, one consistent accent, clear focus states and ample spacing. Do not add decorative charts with fabricated analytics.

### Accounts page

- App title and signed-in staff/logout controls.
- Search by customer name/account number with pagination.
- Primary Create account action.
- Columns: customer, account number, KES balance, active/dormant status and View action.
- Helpful empty and no-results states.
- Creation form requests only customer name; display generated number after success.

### Account detail page

- Back link; customer name, account number, balance and activity date.
- Deposit, Withdraw, Transfer and Create loan actions.
- Loan panel with loan number, principal, outstanding balance and disbursement date.
- Paginated history showing reference, time, operation, incoming/outgoing direction, counterparty and amount.
- Distinguish outgoing/incoming in text, not colour alone.
- Delete action separated from routine money actions, with a confirmation and eligibility reason.
- No loan repayment button or unsupported feature placeholders.

### Interaction requirements

- Use reusable dialogs/forms, field labels and inline validation.
- Transfer dialog shows recipient name and account number before final confirmation.
- Confirmation text includes formatted amount and destination.
- Loan confirmation states exactly KES 10,000 and explains outstanding debt.
- Disable buttons while submitting; keep errors and entered values available after failure.
- Show success reference and refresh balances/history from server responses.
- Do not optimistically change balances before server confirmation.
- Trap focus in dialogs, support Escape and return focus on close; use accessible kit primitives.
- Add responsive table treatment; no clipped controls or horizontal page overflow.
- Format timestamps consistently and label timezone; use Africa/Nairobi for the demo display while storing UTC.
- Handle unexpected errors without displaying SQL, credentials or stack traces.

## 8. Verification matrix

Write meaningful automated tests around domain outcomes, not implementation-mirroring assertions. Run the financial tests on an isolated MySQL test database. Add safeguards so test reset commands cannot touch a normal development or production database.

| ID | Required verification |
|---|---|
| T01 | Account number unique; starting balance zero; invalid name rejected. |
| T02 | Correct deposits/withdrawals including cents; history and activity agree. |
| T03 | Zero, negative, excessive precision, scientific notation and over-limit amounts rejected without writes. |
| T04 | Insufficient withdrawal rejected; exact-balance withdrawal succeeds. |
| T05 | Transfer updates both balances once, writes one reference and preserves combined balance. |
| T06 | Same-account, nonexistent/deleted destination and recipient-overflow transfers rejected. |
| T07 | Inject a controlled failure after an intermediate transfer write: no partial balances, activity or history survive. |
| T08 | Same key/same payload repeated sequentially returns original outcome; different payload/actor rejected. |
| T09 | Loan creates linked records and exactly 1,000,000 cents; repeat same key returns original, new key is rejected. |
| T10 | Inject loan failure after credit/history: no loan, credit or history survives. |
| T11 | Dormancy boundary, never-transacted account fallback, recent activity and month-end calendar cases. |
| T12 | Delete eligible account; reject recent, funded or indebted accounts; history remains retrievable. |
| T13 | Failed financial operations do not refresh dormancy. |
| T14 | Guest access blocked; public registration disabled; CSRF behaviour verified in a browser. |
| T15 | Reconciliation passes for seeded and exercised accounts, including soft-deleted accounts. |
| T16 | React build, TypeScript checks and configured formatter/linter pass. |
| T17 | Browser flow covers all six features, error cases, empty states, keyboard dialogs and mobile layout. |

### Real concurrency verification

Use distinct database connections/processes with controlled overlap; ordinary sequential tests or tests sharing a single outer transaction are insufficient. Put these in a dedicated MySQL integration suite/script:

- Two concurrent withdrawals that each fit alone but exceed the shared available balance: only one succeeds; no negative balance.
- Concurrent identical operation keys: one movement and one history row.
- Opposite-direction transfers: completion or bounded retry, never corrupted balances.
- Deposit versus dormant-account deletion: serialized outcome; never money credited into an already deleted account.
- Two concurrent loan requests with different keys: only one loan and one credit.

State exact setup, commands and results in reporting.md. If the environment cannot run them, mark them **not verified** and do not claim complete concurrency testing. Source-code review is not execution evidence.

## 9. Demo data and reconciliation

Seed fictional customers only. Provide an active funded account, active recipient, new zero-balance account, dormant zero-balance account, dormant funded account and loan account. Use matching transactions for every seeded nonzero balance. Backdate activity and transactions coherently for dormant examples. Seed deterministically into a clean local database without duplicating movements on accidental repeat runs.

Provide a documented local reset path and clearly label commands that erase local data. Never run destructive reset commands against an unknown database.

Demo sequence after reset:

| Step | Expected result |
|---|---|
| Create A and B | Both zero |
| Deposit 5,000 into A | A = 5,000 |
| Withdraw 1,000 from A | A = 4,000 |
| Transfer 1,500 A to B | A = 2,500; B = 1,500 |
| Attempt withdrawal of 5,000 from A | Rejected; balances unchanged |
| Disburse loan to A | A = 12,500; debt = 10,000 |
| Request another loan | Rejected |
| Delete seeded eligible dormant account | Soft-deleted; historical links retained |
| Run reconciliation | No mismatches |

## 10. Deliverables and acceptance gates

| ID | Deliverable | Dependencies | Acceptance evidence |
|---|---|---|---|
| D00 | Repository/environment audit and reporting.md | None | Existing state, versions, decisions and baseline checks recorded |
| D01 | Laravel/React/MySQL foundation and staff access | D00 | App boots, DB connects, login works, guests blocked |
| D02 | Schema, money helper, models and coherent seed data | D01 | Migrations pass on isolated DB; parser tests and seed reconciliation pass |
| D03 | Account creation and read pages | D02 | UI creates zero-balance unique account and renders persisted state |
| D04 | Deposit and withdrawal | D03 | Success/failure, precision and duplicate-submission checks pass |
| D05 | Atomic transfers | D04 | Conservation, rollback, replay and concurrency evidence |
| D06 | Dormancy and soft deletion | D05 | Clock boundaries, eligibility, preserved history and race checks |
| D07 | Loan account and fixed disbursement | D06 | Exact credit, linked records, rollback and duplicate-loan checks |
| D08 | Interface polish and full verification | D07 | Full matrix status, browser screenshots, responsive/accessibility inspection |
| D09 | README, recording guide and clean-clone verification | D08 | Independent local setup works; instructions and limitations accurate |
| D10 | GitHub publication and submission handoff | D09 | If authorized: public URL verified logged out; otherwise ready_to_publish with blocker stated |

Start recording meaningful implementation segments during D01–D07, not only after completion. Capture genuine decisions and debugging. Do not let video editing delay critical correctness work.

Do not mark a milestone complete because its code merely exists. Fix its failed acceptance gate first, or mark it blocked and explicitly identify any independent work that can continue.

## 11. Git discipline and final repository contents

Commit small logical changes after relevant checks. Suggested subjects: scaffold staff dashboard; add banking schema; implement validated deposits and withdrawals; add atomic transfers; enforce dormant deletion; add fixed loan disbursement; verify transaction invariants; document reviewer setup.

Required repository files:

- Application source, migrations, seeders and tests.
- Dependency manifests and lockfiles.
- `.env.example` with placeholders and safe demo settings.
- `.gitignore` excluding `.env`, vendor, node_modules, runtime logs and local DB files.
- `README.md` with actual supported versions, setup, login, commands, screenshots, rules and limitations.
- `reporting.md` with evidence and defect history.
- `docs/recording-guide.md` with a truthful walkthrough outline.
- This specification under `docs/BUILD_PLAN.md` or equivalent.
- Useful UI screenshots under `docs/screenshots/`, without real personal data.

Do not commit credentials, access tokens, actual customer records or the large recording file. A clearly documented local-only seeded demo password is acceptable; production use is outside scope.

Use existing project quality scripts, and add a CI workflow running appropriate checks with MySQL if practical. Record whether CI actually ran; local checks are not proof of remote CI success.

## 12. README requirements and fresh-clone check

Document prerequisites, install commands, environment setup, MySQL database creation, key generation, migrations, seeders, local app startup, test DB configuration, test/build commands and demo login. Explain all business assumptions, integer money, atomicity, locking, replay protection, soft deletion and demo limitations.

Verify from a separate temporary clone/worktree against a separate empty local database using only the written instructions. Record any missing step, correct the README and repeat the failed portion. Never use an existing configured database as evidence of fresh-clone reproducibility.

Include an explicit feature checklist tied to employer requirements and a short architecture explanation. State loan repayment, real payments and production security/compliance are outside scope. List unresolved defects honestly.

## 13. Candidate recording and publication

The candidate must personally explain the implementation using https://webcamera.io/ as requested by the employer. Test recording capabilities and legibility first; do not assume it records the screen in the current environment. If the requested tool cannot capture needed content, flag the issue for the candidate instead of inventing a successful recording.

Provide prompts covering requirements, schema, integer amounts, transfer atomicity, validation, one genuine debugging example and the end-to-end demo. Use plain client language. Candidate should acknowledge assistance accurately and understand all code discussed.

Final video must be playable, audible and below 100 MB. Review it after compression if compression is used. The agent may prepare a guide but must not mark recording/submission done without evidence.

Before authorized publication, verify the intended GitHub owner/repository, inspect the remote URL and check staged content for secrets. Do not force-push or overwrite unrelated repository history. After publishing, verify anonymous visibility and save the exact URL to reporting.md. A publicly visible GitHub repository does not imply a deployed website; label each accurately.

## 14. Failure handling and review loop

For every defect, log an ID (BUG-001 onward), severity, reproduction, expected versus actual behaviour, cause, correction, regression coverage and retest evidence. Preserve the original finding when closing it.

- Critical: money corruption, duplicate movement, unauthorized financial write, leaked secret.
- High: missing required feature, failed rollback, broken installation, deleted history.
- Medium: misleading UI, validation inconsistency, inaccessible core control.
- Low: non-blocking visual or wording issue.

No final completion claim with open critical/high defects or unimplemented employer requirements. Security/correctness fixes may invalidate earlier evidence: mark affected deliverables needs_correction, retest and update their status. Do not silently change requirements to fit implementation.

At each handoff, report: completed deliverables, checks actually run, open issues, next concrete action and any input needed. No optimistic completion percentages without evidence.

## 15. Required reporting.md template

Create this root file before coding. Keep the summary current and append dated deliverable/defect records. Replace template placeholders with facts or `not yet known`; do not leave fictional successful evidence.

```markdown
# Banking Project Progress Report

## Current state
- Last updated: YYYY-MM-DD HH:MM UTC
- Current milestone: D00
- Overall state: not_started
- Branch / latest verified commit: not yet known
- Working tree changes: not yet inspected
- Next action: inspect repository and runtime
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
| D00 | Audit and reporting | not_started | — | — |
| D01 | Foundation and authentication | not_started | — | — |
| D02 | Schema, money and seed data | not_started | — | — |
| D03 | Account creation and views | not_started | — | — |
| D04 | Deposits and withdrawals | not_started | — | — |
| D05 | Transfers | not_started | — | — |
| D06 | Dormancy and deletion | not_started | — | — |
| D07 | Loan disbursement | not_started | — | — |
| D08 | UI and verification | not_started | — | — |
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
```

## 16. Reference documentation

Verify documentation against installed versions before implementing; the specification's business rules take precedence over examples in documentation.

- Laravel React starter kit: https://laravel.com/docs/13.x/starter-kits
- Laravel installation: https://laravel.com/docs/13.x/installation
- Database transactions: https://laravel.com/docs/13.x/database
- Query builder and pessimistic locking: https://laravel.com/docs/13.x/queries
- Employer-requested recorder: https://webcamera.io/

## Agent start instruction

Begin with D00. Create reporting.md, inspect the workspace and record verified environment details. Then implement D01 through D09, updating the report at each deliverable and correcting failed acceptance checks. Prepare D10 and perform publication only with authorization. Finish with a concise evidence-backed handoff linked to reporting.md.
