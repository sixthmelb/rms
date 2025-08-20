<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_id')->unique()->after('email');
            $table->foreignId('department_id')->nullable()->constrained();
            $table->string('position')->nullable();
            $table->string('signature_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['employee_id', 'department_id', 'position', 'signature_path']);
        });
    }
};
