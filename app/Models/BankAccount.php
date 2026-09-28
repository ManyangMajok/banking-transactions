<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['balance_minor' => 'integer', 'last_activity_at' => 'immutable_datetime'];
    }

    public function loan(): HasOne
    {
        return $this->hasOne(Loan::class);
    }

    public function dormant(): bool
    {
        return ($this->last_activity_at ?? $this->created_at)->lte(now()->subMonthsNoOverflow(12));
    }

    public function deletionReason(): ?string
    {
        if (! $this->dormant()) {
            return 'Requires at least 12 calendar months without money movement.';
        }
        if ($this->balance_minor !== 0) {
            return 'The account must have a zero balance.';
        }
        if (($this->loan?->outstanding_minor ?? 0) > 0) {
            return 'An outstanding loan prevents deletion.';
        }

        return null;
    }

    public function display(): array
    {
        return [...$this->only('id', 'account_number', 'customer_name', 'balance_minor', 'last_activity_at', 'created_at'),
            'dormant' => $this->dormant(), 'deletion_reason' => $this->deletionReason()];
    }
}
