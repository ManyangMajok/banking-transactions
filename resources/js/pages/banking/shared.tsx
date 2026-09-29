import { Button } from '@/components/ui/button';
import { Head, Link, usePage } from '@inertiajs/react';
import { Landmark, LogOut, ShieldCheck } from 'lucide-react';
import { ReactNode } from 'react';

export type Account = {
    id: number;
    customer_name: string;
    account_number: string;
    balance_minor: number;
    dormant: boolean;
    last_activity_at: string | null;
    created_at: string;
    deleted_at: string | null;
    deletion_reason: string | null;
};
export type PageData<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
export const money = (minor: number) => new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES' }).format(minor / 100);
export const date = (value: string) =>
    new Intl.DateTimeFormat('en-KE', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Africa/Nairobi' }).format(new Date(value));

export function Shell({ title, children }: { title: string; children: ReactNode }) {
    const { auth, flash } = usePage<{ auth: { user: { name: string } }; flash: { success?: string } }>().props;
    return (
        <div className="min-h-dvh bg-slate-50 text-slate-900">
            <Head title={title} />
            <a href="#main" className="sr-only focus:not-sr-only focus:block focus:p-3">
                Skip to content
            </a>
            <header className="border-b bg-white">
                <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-5 sm:px-8">
                    <Link href="/accounts" className="flex items-center gap-3">
                        <span className="rounded-xl bg-emerald-800 p-2.5 text-white">
                            <Landmark aria-hidden className="size-6" />
                        </span>
                        <span className="text-xl font-semibold">
                            Kijani<span className="ml-2 text-sm font-normal text-slate-500">Banking desk</span>
                        </span>
                    </Link>
                    <div className="flex items-center gap-4 text-sm">
                        <span className="text-slate-600">{auth.user.name}</span>
                        <Link href="/logout" method="post" as="button" className="flex items-center gap-2 rounded-md p-2 hover:bg-slate-100">
                            <LogOut className="size-4" aria-hidden /> Log out
                        </Link>
                    </div>
                </div>
            </header>
            <main id="main" className="mx-auto max-w-7xl px-5 py-8 sm:px-8 sm:py-10">
                <div className="mb-8 flex items-center gap-2 text-xs font-medium text-slate-600">
                    <ShieldCheck className="size-4 text-emerald-800" aria-hidden /> STAFF WORKSPACE <span className="mx-1 text-slate-300">/</span>{' '}
                    SIMULATED MONEY
                </div>
                {flash.success && (
                    <div role="status" className="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm break-words text-emerald-950">
                        {flash.success}
                    </div>
                )}
                {children}
            </main>
            <footer className="mx-auto max-w-7xl px-5 py-8 text-xs text-slate-500 sm:px-8">
                Fictional customer data · KES only · Times shown in Africa/Nairobi (EAT)
            </footer>
        </div>
    );
}

export function Pagination({ page }: { page: PageData<unknown> }) {
    return (
        <nav aria-label="Pagination" className="flex items-center justify-between gap-3 border-t p-5 text-sm">
            <span className="text-slate-500">
                Page {page.current_page} of {page.last_page} · {page.total} records
            </span>
            <div className="flex gap-2">
                {page.prev_page_url && (
                    <Button variant="outline" asChild>
                        <Link href={page.prev_page_url}>Previous</Link>
                    </Button>
                )}
                {page.next_page_url && (
                    <Button variant="outline" asChild>
                        <Link href={page.next_page_url}>Next</Link>
                    </Button>
                )}
            </div>
        </nav>
    );
}

export function Status({ dormant }: { dormant: boolean }) {
    return (
        <span
            className={
                dormant
                    ? 'rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600'
                    : 'rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-800'
            }
        >
            {dormant ? 'Dormant' : 'Active'}
        </span>
    );
}
