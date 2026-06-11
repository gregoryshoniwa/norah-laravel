<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('charges', 'merchant_user_id')) {
            return;
        }

        Schema::table('charges', function (Blueprint $table) {
            $table->unsignedBigInteger('merchant_user_id')->nullable()->after('merchant_user_name');
            $table->index('merchant_user_id');
            $table->foreign('merchant_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            if (Schema::hasColumn('charges', 'merchant_user_id')) {
                $table->dropForeign(['merchant_user_id']);
                $table->dropIndex(['merchant_user_id']);
                $table->dropColumn('merchant_user_id');
            }
        });
    }
};
