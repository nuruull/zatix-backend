<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_method_category_id');
            $table->unsignedBigInteger('bank_id');
            $table->boolean('is_active');
            $table->boolean('is_maintenance');
            $table->integer('priority');
            $table->timestamps();

            $table->foreign('payment_method_category_id')->references('id')->on('payment_method_categories')->onDelete('cascade');
            $table->foreign('bank_id')->references('id')->on('banks')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
