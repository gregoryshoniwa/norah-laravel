<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUserEmailToSystemChargesTable extends Migration
{
    public function up()
    {
        Schema::table('system_charges', function (Blueprint $table) {
            $table->string('user_email')->nullable()->after('pl_account'); // Add user_email column
            $table->foreign('user_email')->references('email')->on('users')->onDelete('cascade'); // Add foreign key constraint
        });
    }

    public function down()
    {
        Schema::table('system_charges', function (Blueprint $table) {
            $table->dropForeign(['user_email']); // Drop foreign key
            $table->dropColumn('user_email'); // Drop user_email column
        });
    }
}
