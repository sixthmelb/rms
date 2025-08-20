<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->enum('role', ['section_head', 'scm_head', 'pjo']);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('comments')->nullable();
            $table->string('qr_code_path')->nullable();
            $table->string('qr_code_data')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            
            $table->unique(['request_id', 'role']);
            $table->index(['status', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};