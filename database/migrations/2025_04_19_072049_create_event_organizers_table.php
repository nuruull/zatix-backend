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
        Schema::create('event_organizers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('eo_owner_id');
            $table->string('name');
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->text('email_eo')->nullable();
            $table->text('phone_no_eo')->nullable();
            $table->text('address_eo')->nullable();
            $table->timestamps();

            $table->foreign('eo_owner_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_organizers');
    }
};
