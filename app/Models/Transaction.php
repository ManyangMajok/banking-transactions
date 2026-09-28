<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $hidden = ['request_hash', 'idempotency_key'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'source_account_id')->withTrashed();
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'destination_account_id')->withTrashed();
    }
}
