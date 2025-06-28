<?php

use App\Enum\Status\OrderStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary()->unique();
            $table->unsignedBigInteger('user_id'); //customer
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('gross_amount'); // Harga total sebelum diskon/pajak
            $table->unsignedBigInteger('discount_amount')->default(0);// Total potongan dari voucher
            $table->unsignedBigInteger('tax_amount')->default(0); // Pajak jika ada
            $table->unsignedBigInteger('net_amount'); // Total akhir ang harus dibayar
            $table->string('status')->default(OrderStatusEnum::UNPAID->value); // unpaid, paid, expired, cancelled
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
