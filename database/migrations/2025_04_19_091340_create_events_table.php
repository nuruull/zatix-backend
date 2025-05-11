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
            $table->enum('status', ['draft', 'active', 'completed'])->default('draft');
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('contact_phone');
            $table->unsignedBigInteger('tnc_id');
            $table->boolean('is_accepted')->default(false);
            $table->timestamps();
            
            $table->foreign('eo_id')->references('id')->on('event_organizers')->onDelete('cascade');
            $table->foreign('tnc_id')->references('id')->on('terms_and_cons')->onDelete('cascade');
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
