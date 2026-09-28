<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('account_number', 32)->unique();
            $table->string('customer_name', 120);
            $table->bigInteger('balance_minor')->default(0);
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('transactions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->uuid('reference')->unique();
            $table->enum('type', ['deposit', 'withdrawal', 'transfer', 'loan_disbursement']);
            $table->foreignId('source_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignId('destination_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->char('request_hash', 64);
            $table->timestamp('created_at');
            $table->index(['source_account_id', 'created_at']);
            $table->index(['destination_account_id', 'created_at']);
        });
        Schema::create('loans', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('loan_number', 40)->unique();
            $table->foreignId('bank_account_id')->unique()->constrained()->restrictOnDelete();
            $table->bigInteger('principal_minor');
            $table->bigInteger('outstanding_minor');
            $table->foreignId('disbursement_transaction_id')->unique()->constrained('transactions')->restrictOnDelete();
            $table->timestamp('disbursed_at');
            $table->timestamps();
        });
        DB::statement('ALTER TABLE bank_accounts ADD CONSTRAINT balance_nonnegative CHECK (balance_minor >= 0)');
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transaction_shape CHECK (amount_minor > 0 AND ((type IN ('deposit', 'loan_disbursement') AND source_account_id IS NULL AND destination_account_id IS NOT NULL) OR (type = 'withdrawal' AND source_account_id IS NOT NULL AND destination_account_id IS NULL) OR (type = 'transfer' AND source_account_id IS NOT NULL AND destination_account_id IS NOT NULL AND source_account_id <> destination_account_id)))");
        DB::statement('ALTER TABLE loans ADD CONSTRAINT loan_amounts CHECK (principal_minor = 1000000 AND outstanding_minor >= 0 AND outstanding_minor <= principal_minor)');
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('bank_accounts');
    }
};
