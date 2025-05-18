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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('eo_id');
            $table->string('name');
            // $table->text('slug')->unique();
            $table->string('poster')->nullable();
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->time('start_time');
            $table->date('end_date');
            $table->time('end_time');
            $table->string('location');
            $table->string('status')->default('draft');
            $table->string('approval_status')->default('pending');
            $table->boolean('is_published')->default(false);
            $table->boolean('is_public')->default(false);
            $table->string('contact_phone');
            $table->timestamps();

            $table->foreign('eo_id')->references('id')->on('event_organizers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
