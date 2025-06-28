<?php

use App\Enum\Status\TransactionStatusEnum;
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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_id');
            $table->unsignedBigInteger('user_id'); //customer
            $table->integer('version_of_payment')->default(1);
            $table->unsignedBigInteger('grand_discount')->default(0);
            $table->unsignedBigInteger('grand_amount');
            $table->string('type'); //cash atau tf
            $table->string('status')->default(TransactionStatusEnum::PENDING->value);
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
