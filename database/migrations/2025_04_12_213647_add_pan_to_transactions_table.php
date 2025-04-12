<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPanToTransactionsTable extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('pan')->nullable()->after('type'); // Add the 'pan' column
            $table->string('expiry_date')->nullable()->after('pan'); // Add the 'expiry_date' column
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('expiry_date'); // Remove the 'expiry_date' column if rolled back
            $table->dropColumn('pan'); // Remove the 'pan' column if rolled back
        });
    }
}
