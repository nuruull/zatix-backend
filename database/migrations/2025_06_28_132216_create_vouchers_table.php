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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); //admin
            $table->string('name');
            $table->string('code');
            $table->string('discount_type'); //percentage, fixed
            $table->string('discount_value');
            $table->unsignedBigInteger('max_amount');
            $table->integer('usage_limit');
            $table->dateTime('valid_until');
            $table->boolean('is_active');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
