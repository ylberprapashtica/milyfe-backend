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
        Schema::create('note_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_capture_id')->constrained('captures')->onDelete('cascade');
            $table->foreignId('target_capture_id')->constrained('captures')->onDelete('cascade');
            $table->timestamps();
            
            // Unique constraint to prevent duplicate links
            $table->unique(['source_capture_id', 'target_capture_id']);
            
            // Indexes for fast lookups
            $table->index('source_capture_id');
            $table->index('target_capture_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('note_links');
    }
};
