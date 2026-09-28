import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Link, router, useForm } from '@inertiajs/react';
import { ArrowUpRight, Plus, Search, Users, Wallet } from 'lucide-react';
import { useState } from 'react';
import { Account, money, PageData, Pagination, Shell, Status } from './shared';

export default function Accounts({
    accounts,
    search,
    summary,
}: {
    accounts: PageData<Account>;
    search: string;
    summary: { count: number; balance_minor: number };
}) {
    const [query, setQuery] = useState(search);
    const [open, setOpen] = useState(false);
    const form = useForm({ customer_name: '' });
    return (
        <Shell title="Accounts">
            <div className="mb-8 flex flex-wrap items-end justify-between gap-5">
                <div>
                    <h1 className="text-3xl font-semibold text-balance">Customer accounts</h1>
                    <p className="mt-2 text-sm text-pretty text-slate-500">A clear view of your customers and their everyday banking.</p>
                </div>
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogTrigger asChild>
                        <Button className="bg-emerald-800 hover:bg-emerald-900">
                            <Plus aria-hidden className="size-4" /> Create account
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>Create a bank account</DialogTitle>
                        <DialogDescription>
                            Enter the customer’s name. The account starts at KES 0.00 and receives a unique account number.
                        </DialogDescription>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post('/accounts', {
                                    onSuccess: () => {
                                        setOpen(false);
                                        form.reset();
                                    },
                                });
                            }}
                            className="space-y-5"
                        >
                            <div className="space-y-2">
                                <Label htmlFor="customer_name">Customer name</Label>
                                <Input
                                    id="customer_name"
                                    required
                                    maxLength={120}
                                    value={form.data.customer_name}
                                    onChange={(e) => form.setData('customer_name', e.target.value)}
                                    aria-invalid={!!form.errors.customer_name}
                                    aria-describedby="name-error"
                                />
                                <p id="name-error" role="alert" className="text-sm text-red-700">
                                    {form.errors.customer_name}
                                </p>
                            </div>
                            <Button disabled={form.processing} type="submit">
                                {form.processing ? 'Creating…' : 'Create account'}
                            </Button>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
            <div className="mb-8 grid gap-4 sm:grid-cols-2">
                <div className="rounded-xl border bg-white p-6">
                    <div className="mb-4 flex items-center justify-between text-sm text-slate-500">
                        Open accounts
                        <Users aria-hidden className="size-5" />
                    </div>
                    <p className="text-3xl font-semibold tabular-nums">{summary.count}</p>
                    <p className="mt-2 text-xs text-slate-500">Active and dormant customer accounts</p>
                </div>
                <div className="rounded-xl border bg-white p-6">
                    <div className="mb-4 flex items-center justify-between text-sm text-slate-500">
                        Total customer balance
                        <Wallet aria-hidden className="size-5" />
                    </div>
                    <p className="text-3xl font-semibold tabular-nums">{money(summary.balance_minor)}</p>
                    <p className="mt-2 text-xs text-slate-500">Simulated funds across open accounts</p>
                </div>
            </div>
            <section className="overflow-hidden rounded-xl border bg-white" aria-label="Customer account directory">
                <div className="flex flex-wrap items-center justify-between gap-4 border-b p-5">
                    <h2 className="font-semibold">Account directory</h2>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.get('/accounts', { search: query }, { preserveState: true });
                        }}
                        className="flex w-full gap-2 sm:w-auto"
                    >
                        <Label htmlFor="search" className="sr-only">
                            Search by name or account number
                        </Label>
                        <Input
                            id="search"
                            className="sm:w-72"
                            placeholder="Search name or account number"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            maxLength={120}
                        />
                        <Button type="submit" variant="outline" aria-label="Search accounts">
                            <Search aria-hidden className="size-4" />
                        </Button>
                    </form>
                </div>
                {accounts.data.length ? (
                    <>
                        <div className="hidden grid-cols-12 gap-4 bg-slate-50 px-6 py-3 text-xs font-medium text-slate-500 md:grid">
                            <span className="col-span-5">CUSTOMER / ACCOUNT</span>
                            <span className="col-span-3">AVAILABLE BALANCE</span>
                            <span className="col-span-2">STATUS</span>
                            <span className="col-span-2 text-right">DETAILS</span>
                        </div>
                        <ul className="divide-y">
                            {accounts.data.map((account) => (
                                <li key={account.id} className="grid items-center gap-4 p-6 md:grid-cols-12">
                                    <div className="min-w-0 md:col-span-5">
                                        <p className="font-medium">{account.customer_name}</p>
                                        <p className="mt-1 text-xs break-all text-slate-500">{account.account_number}</p>
                                    </div>
                                    <p className="font-medium tabular-nums md:col-span-3">{money(account.balance_minor)}</p>
                                    <div className="md:col-span-2">
                                        <Status dormant={account.dormant} />
                                    </div>
                                    <Link
                                        className="flex items-center gap-1 text-sm font-medium text-emerald-800 md:col-span-2 md:justify-end"
                                        href={`/accounts/${account.id}`}
                                        aria-label={`View ${account.customer_name}`}
                                    >
                                        View account <ArrowUpRight aria-hidden className="size-4" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </>
                ) : (
                    <div className="p-12 text-center">
                        <h3 className="font-semibold">{search ? 'No matching accounts' : 'Your first customer starts here'}</h3>
                        <p className="mt-2 text-sm text-slate-500">
                            {search ? 'Try another name or account number.' : 'Create an account to begin recording transactions.'}
                        </p>
                        <Button variant="outline" className="mt-5" onClick={() => (search ? router.get('/accounts') : setOpen(true))}>
                            {search ? 'Clear search' : 'Create account'}
                        </Button>
                    </div>
                )}
                <Pagination page={accounts} />
            </section>
        </Shell>
    );
}
