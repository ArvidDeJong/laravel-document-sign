<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signer_signers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('document_id')->constrained('signer_documents')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('status')->default('pending');
            $table->unsignedInteger('page')->default(1);
            $table->decimal('x', 5, 2)->default(10);
            $table->decimal('y', 5, 2)->default(80);
            $table->decimal('width', 5, 2)->nullable();
            $table->string('signature_path')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signer_signers');
    }
};
