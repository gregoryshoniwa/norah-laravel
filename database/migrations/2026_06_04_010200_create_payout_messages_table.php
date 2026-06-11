<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payout_messages')) {
            return;
        }

        Schema::create('payout_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payout_id')->nullable();
            $table->unsignedBigInteger('recipient_user_id'); // the merchant/admin the thread belongs to
            $table->unsignedBigInteger('sender_user_id');
            $table->string('sender_role'); // MERCHANT | ADMIN | SUPER
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->unsignedBigInteger('parent_message_id')->nullable();
            $table->string('status')->default('OPEN'); // OPEN | RESOLVED
            $table->timestamps();

            $table->index('payout_id');
            $table->index('recipient_user_id');
            $table->index('sender_user_id');
            $table->index('parent_message_id');
            $table->index(['recipient_user_id', 'status']);

            $table->foreign('payout_id')->references('id')->on('payouts')->nullOnDelete();
            $table->foreign('recipient_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('sender_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('parent_message_id')->references('id')->on('payout_messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_messages');
    }
};
