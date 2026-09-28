# Candidate recording guide

The candidate must personally explain and record the implementation. No candidate video has been recorded by the agent. Do not describe generated narration as the candidate's work.

## Before recording

Open https://webcamera.io/ as requested by the employer. Make a short camera/audio test, play it back and check legibility. Confirm whether the tool offers screen capture in your browser; camera recording alone may not show the application. If screen capture is unavailable, clarify the employer's acceptable recording method. Do not assume a screen recording succeeded. Camera/microphone permissions and the actual recording are the candidate's steps.

Keep passwords, environment files, personal tabs and unrelated databases off screen. Use only the fictional seeded customers. Target a concise 5–8 minute explanation and verify the final playable file is below 100 MB. If compressed, play the compressed result and check audio and code readability.

## Explain the actual implementation

1. **Scope:** a staff-operated assessment with simulated KES money. Staff users are separate from customer accounts. Explain that this is not production banking or a complete double-entry ledger.
2. **Architecture:** Laravel routes and Form Requests validate requests; thin controllers call services; React/Inertia renders the server's confirmed state. Show `BankingService.php` and the schema migration.
3. **Amounts:** “I store whole cents, so 10,000 shillings is 1,000,000 cents. Invalid precision is rejected, never rounded.” Show the parser and configurable demo cap.
4. **Transfers:** “Both accounts are locked in ID order. Debit, credit, activity and history commit together. A failure rolls everything back.” Show the injected-failure test and explain why a sequential test is not concurrency evidence.
5. **Duplicate requests:** “A UUID identifies one intended operation. The server compares the canonical payload and returns the original reference on retry.” Explain why disabling a button alone is insufficient.
6. **Dormancy and deletion:** 12 calendar months, including month-end handling; only zero balance and no debt can be soft-deleted. Dormant accounts may transact and reactivate.
7. **Loan:** one loan ever per account, fixed server-side KES 10,000, atomic credit and linked debt record; no repayment functionality.
8. **A genuine debugging example:** the packaged starter had incompatible form interfaces after dependency updates. TypeScript exposed this and aliases fixed it. The HTTP test fixtures also passed UUID objects instead of the browser's strings; the fixtures were corrected without relaxing validation. The original XAMPP instance reported a corrupt privilege table and later stopped; explain the isolated MySQL fallback only once verified in reporting.md.
9. **Evidence:** show the actual passing tests, concurrent-process checks and `php artisan banking:reconcile`; distinguish local checks from remote CI.
10. **Assistance:** accurately acknowledge tool/AI assistance and describe decisions you understand. Do not claim sole authorship or claim to have recorded development segments that were not captured.

## Live demo

Create A and B at zero. Deposit 5,000 to A, withdraw 1,000, transfer 1,500 to B; show A=2,500 and B=1,500. Reject a 5,000 withdrawal from A. Create the loan: A=12,500 and debt=10,000. Explain the one-loan rule. Delete the seeded eligible dormant account; explain why funded/recent/indebted accounts cannot be deleted. Show reconciliation.

## Final checks

- Play the entire recording, check audio and visibility, and inspect its actual file size.
- Verify the exact public GitHub URL while logged out after publication is authorized.
- A public source repository is not a deployed website.
- Record the real repository and video links in reporting.md; submission remains the candidate's responsibility.
