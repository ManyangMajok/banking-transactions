<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoneyOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $rules = ['idempotency_key' => ['required', 'uuid'], 'balance_minor' => ['prohibited']];
        if (! $this->routeIs('accounts.loan')) {
            $rules['amount'] = ['required', 'string', 'max:12'];
        }
        if ($this->routeIs('transfers.store')) {
            $rules['source_account_id'] = ['required', 'integer', 'min:1'];
            $rules['destination_account_id'] = ['required', 'integer', 'min:1', 'different:source_account_id'];
        }

        return $rules;
    }
}
