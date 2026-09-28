import { Button } from '@/components/ui/button';
import { Link, useForm } from '@inertiajs/react';
import { ArrowDownLeft, ArrowLeft, ArrowUpRight, Wallet } from 'lucide-react';
import { useState } from 'react';
import Operation, { Confirmation } from './operation';
import { Account, date, money, PageData, Pagination, Shell, Status } from './shared';
type Movement = {
    id: number;
    reference: string;
    type: string;
    created_at: string;
    amount_minor: number;
    source_account_id: number | null;
    destination_account_id: number | null;
    source: Account | null;
    destination: Account | null;
};
type Loan = { loan_number: string; principal_minor: number; outstanding_minor: number; disbursed_at: string };
export default function Detail({
    account,
    history,
    loan,
    recipients,
}: {
    account: Account;
    history: PageData<Movement>;
    loan: Loan | null;
    recipients: Account[];
}) {
    const [deleting, setDeleting] = useState(false);
    const form = useForm({ operation: '' });
    return (
        <Shell title={account.customer_name}>
            <Link href="/accounts" className="mb-6 inline-flex items-center gap-2 text-sm text-slate-600">
                <ArrowLeft aria-hidden className="size-4" /> All accounts
            </Link>
            <div className="mb-8 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-semibold text-balance">{account.customer_name}</h1>
                    <p className="mt-2 text-sm break-all text-slate-500">{account.account_number}</p>
                </div>
                <Status dormant={account.dormant} />
            </div>
            <div className="mb-8 grid gap-6 lg:grid-cols-3">
                <section className="rounded-xl border bg-white p-6 sm:p-8 lg:col-span-2">
                    <div className="flex items-center justify-between text-sm text-slate-500">
                        <h2>Available balance</h2>
                        <Wallet aria-hidden className="size-5 text-emerald-800" />
                    </div>
                    <p className="mt-4 text-4xl font-semibold tabular-nums">{money(account.balance_minor)}</p>
                    <p className="mt-3 text-xs text-slate-500">
                        {account.last_activity_at
                            ? `Last movement: ${date(account.last_activity_at)} EAT`
                            : `No movements · Opened ${date(account.created_at)} EAT`}
                    </p>
                    <div className="mt-8 flex flex-wrap gap-3">
                        {(['deposit', 'withdrawal', 'transfer'] as const).map((kind) => (
                            <Operation key={kind} kind={kind} account={account} recipients={recipients} />
                        ))}
                    </div>
                </section>
                <section className="rounded-xl border bg-white p-6">
                    <h2 className="font-semibold">Demo loan</h2>
                    {loan ? (
                        <>
                            <p className="mt-4 text-xs break-all text-slate-500">{loan.loan_number}</p>
                            <p className="mt-4 text-xs text-slate-500">OUTSTANDING</p>
                            <p className="mt-1 text-2xl font-semibold tabular-nums">{money(loan.outstanding_minor)}</p>
                            <p className="mt-3 text-xs leading-5 text-slate-500">
                                Principal: {money(loan.principal_minor)}
                                <br />
                                Disbursed {date(loan.disbursed_at)} EAT
                                <br />
                                One demo loan per account. No repayments.
                            </p>
                        </>
                    ) : (
                        <>
                            <p className="mt-4 text-2xl font-semibold tabular-nums">KES 10,000.00</p>
                            <p className="mt-3 mb-6 text-sm leading-6 text-pretty text-slate-500">
                                A single fixed disbursement into this account. An equal outstanding debt is recorded.
                            </p>
                            <Operation kind="loan" account={account} recipients={recipients} />
                        </>
                    )}
                </section>
            </div>
            <section className="overflow-hidden rounded-xl border bg-white">
                <div className="border-b p-6">
                    <h2 className="font-semibold">Transaction history</h2>
                    <p className="mt-1 text-xs text-slate-500">Confirmed movements · Africa/Nairobi (EAT)</p>
                </div>
                {history.data.length ? (
                    <ul className="divide-y">
                        {history.data.map((t) => {
                            const outgoing = t.source_account_id === account.id;
                            const counterparty = outgoing ? t.destination : t.source;
                            return (
                                <li key={t.id} className="flex flex-wrap items-center justify-between gap-4 p-6">
                                    <div className="flex min-w-0 items-start gap-4">
                                        <span className="rounded-lg bg-slate-100 p-2">
                                            {outgoing ? (
                                                <ArrowUpRight className="size-5" aria-hidden />
                                            ) : (
                                                <ArrowDownLeft className="size-5 text-emerald-800" aria-hidden />
                                            )}
                                        </span>
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium capitalize">
                                                {t.type.replaceAll('_', ' ')} · {outgoing ? 'Outgoing' : 'Incoming'}
                                            </p>
                                            <p className="mt-1 text-xs text-slate-500">
                                                {counterparty ? `${outgoing ? 'To' : 'From'} ${counterparty.customer_name}` : 'Staff banking desk'} ·{' '}
                                                {date(t.created_at)} EAT
                                            </p>
                                            <p className="mt-2 text-xs break-all text-slate-400">{t.reference}</p>
                                        </div>
                                    </div>
                                    <p className="font-medium whitespace-nowrap tabular-nums">
                                        {outgoing ? '−' : '+'}
                                        {money(t.amount_minor)}
                                    </p>
                                </li>
                            );
                        })}
                    </ul>
                ) : (
                    <div className="p-10 text-center">
                        <h3 className="font-medium">No transactions yet</h3>
                        <p className="mt-2 text-sm text-slate-500">Make a deposit to record the first movement.</p>
                    </div>
                )}
                <Pagination page={history} />
            </section>
            <section className="mt-8 flex flex-wrap items-center justify-between gap-5 rounded-xl border border-dashed p-6">
                <div>
                    <h2 className="text-sm font-semibold">Close dormant account</h2>
                    <p className="mt-1 max-w-xl text-sm text-slate-500">
                        {account.deletion_reason ??
                            'Eligible: dormant for 12 months, zero balance and no outstanding loan. History will be retained.'}
                    </p>
                    <p role="alert" className="mt-2 text-sm text-red-700">
                        {form.errors.operation}
                    </p>
                </div>
                <Confirmation
                    title="Delete this dormant account?"
                    description={`${account.customer_name} (${account.account_number}) will be removed from the account directory. Historical records remain preserved.`}
                    open={deleting}
                    onOpenChange={setDeleting}
                    processing={form.processing}
                    onConfirm={() => form.delete(`/accounts/${account.id}`, { onError: () => setDeleting(false) })}
                >
                    <Button variant="outline" disabled={!!account.deletion_reason}>
                        Delete account
                    </Button>
                </Confirmation>
            </section>
        </Shell>
    );
}
