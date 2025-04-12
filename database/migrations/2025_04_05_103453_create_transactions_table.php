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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('payment_method')->nullable();
            $table->string('merchant_uid')->nullable(); // Reference to the merchant
            $table->unsignedBigInteger('user_id')->nullable(); // Reference to the user
            $table->string('status')->nullable(); // Enum-like field
            $table->longText('error_message')->nullable();
            $table->string('display_error_message')->nullable();
            $table->string('reference')->nullable();
            $table->string('debit_reference')->nullable();
            $table->string('credit_reference')->nullable();
            $table->string('response_code')->nullable();
            $table->string('trace')->nullable();
            $table->string('statement_narration')->nullable();
            $table->string('type')->nullable(); // Enum-like field
            $table->string('user_type')->nullable(); // Enum-like field
            $table->string('currency')->nullable(); // Enum-like field
            $table->string('amount')->nullable();
            $table->string('charge')->nullable();
            $table->decimal('numeric_amount', 15, 2)->nullable();
            $table->longText('additional_data')->nullable();
            $table->longText('request')->nullable();
            $table->longText('response')->nullable();
            $table->string('user_name')->nullable();
            $table->string('application_id')->nullable();
            $table->string('transaction_status')->nullable();
            $table->string('reference_code')->nullable();
            $table->boolean('deleted')->default(false); // Soft delete equivalent
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
