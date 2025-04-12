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
        Schema::table('system_charges', function (Blueprint $table) {
            $table->string('created_by')->nullable()->after('pl_account'); // Add the created_by column
            $table->string('modified_by')->nullable()->after('created_by'); // Add the modified_by column
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_charges', function (Blueprint $table) {
            $table->dropColumn('modified_by'); // Remove the modified_by column
            $table->dropColumn('created_by'); // Remove the created_by column
        });
    }
};
