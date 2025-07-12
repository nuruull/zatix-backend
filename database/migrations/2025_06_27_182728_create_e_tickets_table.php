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
        Schema::create('e_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_code')->unique(); // Kode unik untuk QR Code
            $table->uuid('order_id');
            $table->unsignedBigInteger('ticket_id'); // Jenis tiketnya
            $table->string('attendee_name')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->unsignedBigInteger('checked_in_by')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('checked_in_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('e_tickets');
    }
};
