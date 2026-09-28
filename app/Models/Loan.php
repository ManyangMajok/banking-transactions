<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['principal_minor' => 'integer', 'outstanding_minor' => 'integer', 'disbursed_at' => 'immutable_datetime'];
    }
}
