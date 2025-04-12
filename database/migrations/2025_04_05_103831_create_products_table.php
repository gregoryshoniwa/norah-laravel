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
        Schema::create('products', function (Blueprint $table) {
            $table->id('product_id'); // Custom primary key
            $table->string('product_name');
            $table->text('product_description')->nullable();
            $table->string('product_price');
            $table->string('product_image')->nullable();
            $table->string('product_status')->nullable();
            $table->unsignedBigInteger('product_type_id'); // Foreign key to product_types
            $table->unsignedBigInteger('merchant_id'); // Foreign key to merchants
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('product_type_id')->references('product_type_id')->on('product_types')->onDelete('cascade');
            $table->foreign('merchant_id')->references('merchant_id')->on('merchants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
