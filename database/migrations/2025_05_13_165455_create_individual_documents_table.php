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
        Schema::create('individual_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doc_type_id');
            $table->string('ktp_file');
            $table->string('ktp_number');
            $table->string('ktp_name');
            $table->text('ktp_address');
            $table->string('npwp_file')->nullable();
            $table->string('npwp_number')->nullable();
            $table->string('npwp_name')->nullable();
            $table->text('npwp_address')->nullable();
            $table->timestamps();

            $table->foreign('doc_type_id')->references('id')->on('document_types')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('individual_documents');
    }
};
