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
        Schema::create('demo_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('eo_name');
            $table->string('eo_email');
            $table->text('eo_description');
            $table->string('event_name');
            $table->text('event_description');
            $table->string('audience_target');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('note')->nullable();
            $table->dateTime('pitching_schedule')->nullable();
            $table->string('pitching_link')->nullable();
            $table->dateTime('demo_access_expiry')->nullable();
            $table->boolean('is_continue')->default(false);
            $table->tinyInteger('current_step')->default(1);
            $table->string('rejected_reason')->nullable();
            $table->boolean('role_updated')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
