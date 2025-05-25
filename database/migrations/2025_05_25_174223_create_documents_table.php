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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable'); //eo_id atau user_id
            $table->string('type'); //ktp, npwp, nib
            $table->string('file');
            $table->string('number');
            $table->string('name');
            $table->string('address');
            $table->string('status')->default('pending'); //pending, verified, rejected
            $table->text('reason_rejected')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
