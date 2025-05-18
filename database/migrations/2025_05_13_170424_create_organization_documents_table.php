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
        Schema::create('organization_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doc_type_id');
            $table->string('npwp_file');
            $table->string('npwp_number');
            $table->string('npwp_name');
            $table->text('npwp_address');
            $table->string('nib_file');
            $table->string('nib_number');
            $table->string('nib_name');
            $table->text('nib_address');
            $table->timestamps();

            $table->foreign('doc_type_id')->references('id')->on('document_types')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_documents');
    }
};
