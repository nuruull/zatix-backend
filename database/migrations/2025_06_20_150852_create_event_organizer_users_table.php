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
        Schema::create('event_organizer_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('eo_id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('user_id');

            $table->foreign('eo_id')->references('id')->on('event_organizers')->onDelete('cascade');
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_organizer_users');
    }
};
