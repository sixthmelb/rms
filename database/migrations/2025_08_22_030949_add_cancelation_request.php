<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add new columns for cancellation and revision
        Schema::table('requests', function (Blueprint $table) {
            $table->text('cancellation_reason')->nullable()->after('notes');
            $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
        });

        // Update enum values for status column
        DB::statement("ALTER TABLE requests MODIFY COLUMN status ENUM(
            'draft', 
            'submitted', 
            'section_approved', 
            'scm_approved', 
            'completed', 
            'rejected',
            'cancelled',
            'revision_requested'
        ) DEFAULT 'draft'");

        // Update approvals status enum as well
        DB::statement("ALTER TABLE approvals MODIFY COLUMN status ENUM(
            'pending', 
            'approved', 
            'rejected',
            'cancelled',
            'revision_requested'
        ) DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Remove new columns
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn(['cancellation_reason', 'cancelled_at']);
        });

        // Revert enum values
        DB::statement("ALTER TABLE requests MODIFY COLUMN status ENUM(
            'draft', 
            'submitted', 
            'section_approved', 
            'scm_approved', 
            'completed', 
            'rejected'
        ) DEFAULT 'draft'");

        DB::statement("ALTER TABLE approvals MODIFY COLUMN status ENUM(
            'pending', 
            'approved', 
            'rejected'
        ) DEFAULT 'pending'");
    }
};