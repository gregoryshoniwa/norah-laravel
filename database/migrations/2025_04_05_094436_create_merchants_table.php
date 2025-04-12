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
        Schema::create('merchants', function (Blueprint $table) {
            $table->id('merchant_id');
            $table->string('merchant_name');
            $table->string('merchant_address')->nullable();
            $table->string('merchant_phone')->nullable();
            $table->string('merchant_email')->nullable();
            $table->string('label')->nullable();
            $table->string('merchant_uid')->unique();
            $table->string('merchant_secret')->nullable();
            $table->string('merchant_status')->nullable(); // Enum-like field
            $table->string('merchant_country')->nullable();
            $table->string('merchant_city')->nullable();
            $table->longText('merchant_logo')->nullable();
            $table->string('merchant_website')->nullable();
            $table->longText('merchant_description')->nullable();
            $table->string('return_url')->nullable();
            $table->string('web_service_url')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Foreign key to users table
            $table->timestamps();
        });

        Schema::create('merchant_charges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id'); // Define the foreign key column
            $table->foreign('merchant_id')->references('merchant_id')->on('merchants')->onDelete('cascade'); // Reference the correct primary key
            $table->foreignId('charge_id')->constrained('charges')->onDelete('cascade'); // Reference the charges table
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_charges');
        Schema::dropIfExists('merchants');
    }
};
