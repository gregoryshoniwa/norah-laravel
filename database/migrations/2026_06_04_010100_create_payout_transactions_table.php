<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payout_transactions')) {
            return;
        }

        Schema::create('payout_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payout_id');
            $table->unsignedBigInteger('transaction_id');
            $table->string('source_type', 30); // PAYMENT | MERCHANT_CHARGE
            $table->decimal('amount', 15, 2); // amount the recipient is being credited for this transaction
            $table->timestamps();

            $table->unique(['payout_id', 'transaction_id', 'source_type']);
            $table->index('transaction_id');
            $table->index(['transaction_id', 'source_type']);

            $table->foreign('payout_id')->references('id')->on('payouts')->cascadeOnDelete();
            $table->foreign('transaction_id')->references('id')->on('transactions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_transactions');
    }
};
