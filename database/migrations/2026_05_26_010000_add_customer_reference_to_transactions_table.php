<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('transactions', 'customer_reference')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('customer_reference')->nullable()->after('reference');
            $table->index('customer_reference');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('transactions', 'customer_reference')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['customer_reference']);
            $table->dropColumn('customer_reference');
        });
    }
};
