<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signer_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('signer_documents')->cascadeOnDelete();
            $table->foreignId('signer_id')->nullable()->constrained('signer_signers')->nullOnDelete();
            $table->string('event');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signer_audit_events');
    }
};
