<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->integer('item_number');
            $table->string('description');
            $table->text('specification');
            $table->integer('quantity');
            $table->string('unit_of_measurement');
            $table->text('remarks')->nullable();
            $table->timestamps();
            
            $table->index(['request_id', 'item_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_items');
    }
};
