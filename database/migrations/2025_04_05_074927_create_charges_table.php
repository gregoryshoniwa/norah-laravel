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
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->string('charge_type');
            $table->string('charge_source');
            $table->string('charge_category');
            $table->string('status');
            $table->string('currency');
            $table->decimal('value', 15, 2);
            $table->string('statement_narration')->nullable();
            $table->decimal('min_threshold', 15, 2)->nullable();
            $table->decimal('max_threshold', 15, 2)->nullable();
            $table->string('pl_account')->unique();
            $table->string('merchant_user_name')->nullable();
            $table->boolean('deleted')->default(false); // Soft delete equivalent
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
