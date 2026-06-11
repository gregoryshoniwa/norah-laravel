<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payout_schedules')) {
            return;
        }

        Schema::create('payout_schedules', function (Blueprint $table) {
            $table->id();
            // Scope: a single recipient, OR a whole role bucket (ADMIN/MERCHANT), OR everyone.
            $table->unsignedBigInteger('recipient_user_id')->nullable();
            $table->string('recipient_role_scope', 20)->nullable(); // 'ADMIN' | 'MERCHANT' | null
            $table->string('currency', 3);

            // Cadence
            $table->string('cadence', 20); // DAILY | WEEKLY | MONTHLY
            $table->unsignedTinyInteger('day_of_week')->nullable(); // 1=Mon..7=Sun, for WEEKLY
            $table->unsignedTinyInteger('day_of_month')->nullable(); // 1..28, for MONTHLY (cap at 28 to avoid month-end edge cases)

            // Behaviour
            $table->decimal('minimum_amount', 15, 2)->default(0);
            $table->unsignedSmallInteger('cutoff_hours_back')->default(24); // only include txns older than this many hours
            $table->string('default_notes', 500)->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamp('last_run_at')->nullable();
            $table->json('last_run_summary')->nullable();

            $table->unsignedBigInteger('created_by_user_id');
            $table->timestamps();

            $table->index(['is_active', 'cadence']);
            $table->index('recipient_user_id');

            $table->foreign('recipient_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_schedules');
    }
};
