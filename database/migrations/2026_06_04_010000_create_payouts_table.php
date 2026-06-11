<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payouts')) {
            return;
        }

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recipient_user_id'); // MERCHANT or ADMIN being paid
            $table->string('recipient_role', 20); // 'ADMIN' or 'MERCHANT'
            $table->string('currency', 3);
            $table->decimal('amount', 15, 2); // total being paid out
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('status', 20)->default('PENDING'); // PENDING / SENT / CONFIRMED / DISPUTED
            $table->string('bank_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_user_id'); // SUPER who created the payout
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->timestamps();

            $table->index('recipient_user_id');
            $table->index(['recipient_user_id', 'status']);
            $table->index('status');
            $table->index('currency');
            $table->index('created_at');

            $table->foreign('recipient_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
