<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('transaction_audits')) {
            return;
        }

        Schema::create('transaction_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('trace')->nullable()->index();
            $table->string('reference')->nullable()->index();
            $table->string('payment_method')->nullable()->index();
            $table->string('stage')->nullable()->index();
            $table->string('event')->index();
            $table->string('level')->default('INFO')->index();
            $table->string('provider')->nullable()->index();
            $table->string('endpoint')->nullable();
            $table->integer('status_code')->nullable();
            $table->longText('request_payload')->nullable();
            $table->longText('response_payload')->nullable();
            $table->longText('meta_data')->nullable();
            $table->timestamps();

            $table->foreign('transaction_id')->references('id')->on('transactions')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_audits');
    }
};

