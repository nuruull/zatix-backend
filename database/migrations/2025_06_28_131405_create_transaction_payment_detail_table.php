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
        Schema::create('transaction_payment_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('payment_method_id');
            $table->string('va_number')->nullable();
            $table->string('bill_key')->nullable();
            $table->string('biller_code')->nullable();
            $table->text('qris_url')->nullable();
            $table->dateTime('expiry_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_payment_detail');
    }
};
