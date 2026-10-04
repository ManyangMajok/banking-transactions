import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/react';
import * as Alert from '@radix-ui/react-alert-dialog';
import { useState } from 'react';
import { Account, money } from './shared';

export function Confirmation({
    title,
    description,
    children,
    onConfirm,
    processing,
    open,
    onOpenChange,
}: {
    title: string;
    description: string;
    children?: React.ReactNode;
    onConfirm: () => void;
    processing: boolean;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Alert.Root open={open} onOpenChange={onOpenChange}>
            {children && <Alert.Trigger asChild>{children}</Alert.Trigger>}
            <Alert.Portal>
                <Alert.Overlay className="fixed inset-0 z-50 bg-black/50" />
                <Alert.Content className="fixed top-1/2 left-1/2 z-50 w-11/12 max-w-md -translate-x-1/2 -translate-y-1/2 rounded-xl border bg-white p-6 text-slate-900 shadow-lg">
                    <Alert.Title className="text-lg font-semibold text-balance">{title}</Alert.Title>
                    <Alert.Description className="mt-3 text-sm leading-6 text-pretty text-slate-600">{description}</Alert.Description>
                    <div className="mt-6 flex justify-end gap-3">
                        <Alert.Cancel asChild>
                            <Button variant="outline" disabled={processing}>
                                Go back
                            </Button>
                        </Alert.Cancel>
                        <Button disabled={processing} onClick={onConfirm}>
                            {processing ? 'Processing…' : 'Confirm'}
                        </Button>
                    </div>
                </Alert.Content>
            </Alert.Portal>
        </Alert.Root>
    );
}

export default function Operation({
    kind,
    account,
    recipients,
}: {
    kind: 'deposit' | 'withdrawal' | 'transfer' | 'loan';
    account: Account;
    recipients: Pick<Account, 'id' | 'customer_name' | 'account_number'>[];
}) {
    const [open, setOpen] = useState(false);
    const [confirm, setConfirm] = useState(false);
    const label = { deposit: 'Deposit', withdrawal: 'Withdraw', transfer: 'Transfer', loan: 'Create loan account' }[kind];
    const form = useForm({
        amount: '',
        source_account_id: account.id,
        destination_account_id: '',
        idempotency_key: crypto.randomUUID(),
        operation: '',
    });
    const recipient = recipients.find((r) => String(r.id) === form.data.destination_account_id);
    const parsedDisplay = /^\d+(\.\d{1,2})?$/.test(form.data.amount) ? money(Number(form.data.amount) * 100) : form.data.amount;
    const description =
        kind === 'loan'
            ? `Create a linked loan account and credit KES 10,000.00 to ${account.customer_name}. This creates KES 10,000.00 of outstanding debt. One loan account is permitted per bank account.`
            : `${label} ${parsedDisplay} ${kind === 'withdrawal' ? 'from' : 'to'} ${kind === 'transfer' ? `${recipient?.customer_name} (${recipient?.account_number})` : `${account.customer_name} (${account.account_number})`}.`;
    const submit = () => {
        const url =
            kind === 'transfer'
                ? '/transfers'
                : `/accounts/${account.id}/${kind === 'deposit' ? 'deposits' : kind === 'withdrawal' ? 'withdrawals' : 'loan'}`;
        form.post(url, {
            preserveScroll: true,
            onError: () => setConfirm(false),
            onSuccess: () => {
                setConfirm(false);
                setOpen(false);
                form.reset();
                form.setData('idempotency_key', crypto.randomUUID());
            },
        });
    };
    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                if (!form.processing) setOpen(value);
            }}
        >
            <DialogTrigger asChild>
                <Button
                    variant={kind === 'deposit' ? 'default' : 'outline'}
                    className={kind === 'deposit' ? 'bg-emerald-800 hover:bg-emerald-900' : ''}
                >
                    {label}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{label}</DialogTitle>
                <DialogDescription>
                    {kind === 'loan'
                        ? 'Create a loan account with a KES 10,000.00 principal and disburse the funds into this bank account.'
                        : `Record a ${kind} for ${account.customer_name}. All amounts are in KES.`}
                </DialogDescription>
                <form
                    className="space-y-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        setConfirm(true);
                    }}
                >
                    {kind !== 'loan' && (
                        <div className="space-y-2">
                            <Label htmlFor={`${kind}-amount`}>Amount (KES)</Label>
                            <Input
                                id={`${kind}-amount`}
                                inputMode="decimal"
                                required
                                pattern="[0-9]+(\.[0-9]{1,2})?"
                                maxLength={12}
                                placeholder="0.00"
                                value={form.data.amount}
                                onChange={(e) => form.setData('amount', e.target.value)}
                                aria-invalid={!!form.errors.amount}
                                aria-describedby={`${kind}-amount-error`}
                            />
                            <p id={`${kind}-amount-error`} className="text-sm text-red-700">
                                {form.errors.amount}
                            </p>
                        </div>
                    )}
                    {kind === 'transfer' && (
                        <div className="space-y-2">
                            <Label htmlFor="recipient">Recipient account</Label>
                            <select
                                id="recipient"
                                required
                                className="w-full rounded-md border bg-white px-3 py-2 text-sm"
                                value={form.data.destination_account_id}
                                onChange={(e) => form.setData('destination_account_id', e.target.value)}
                                aria-invalid={!!form.errors.destination_account_id}
                                aria-describedby="recipient-error"
                            >
                                <option value="">Select a recipient</option>
                                {recipients.map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {r.customer_name} · {r.account_number}
                                    </option>
                                ))}
                            </select>
                            <p id="recipient-error" className="text-sm text-red-700">
                                {form.errors.destination_account_id}
                            </p>
                            {recipient && (
                                <p className="rounded-lg bg-slate-50 p-3 text-sm break-words">
                                    To: {recipient.customer_name}
                                    <br />
                                    {recipient.account_number}
                                </p>
                            )}
                        </div>
                    )}
                    <div role="alert" className="text-sm text-red-700">
                        {form.errors.operation || form.errors.idempotency_key || form.errors.source_account_id}
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Processing…' : 'Review operation'}
                    </Button>
                    <p className="text-xs text-slate-500">
                        If a request is interrupted, retry this form. The same request will not be processed twice.
                    </p>
                </form>
                <Confirmation
                    title={`Confirm ${kind === 'loan' ? 'loan disbursement' : kind}`}
                    description={description}
                    onConfirm={submit}
                    processing={form.processing}
                    open={confirm}
                    onOpenChange={setConfirm}
                />
            </DialogContent>
        </Dialog>
    );
}
