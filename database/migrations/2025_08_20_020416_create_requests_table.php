<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->date('request_date');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained();
            $table->enum('status', [
                'draft', 'submitted', 'section_approved', 
                'scm_approved', 'completed', 'rejected'
            ])->default('draft');
            $table->text('notes')->nullable();
            $table->json('signature_data')->nullable(); // Store QR codes
            $table->timestamps();
            
            $table->index(['status', 'created_at']);
            $table->index('request_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};