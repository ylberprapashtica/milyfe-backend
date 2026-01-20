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
        Schema::table('captures', function (Blueprint $table) {
            // Rename thought to content
            $table->renameColumn('thought', 'content');
            
            // Add new Zettelkasten fields
            $table->string('title')->nullable()->after('content');
            $table->string('slug')->unique()->after('title');
            $table->json('tags')->nullable()->after('slug');
            
            // Add index on slug for fast lookups
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('captures', function (Blueprint $table) {
            // Remove indexes and columns
            $table->dropIndex(['slug']);
            $table->dropColumn(['tags', 'slug', 'title']);
            
            // Rename content back to thought
            $table->renameColumn('content', 'thought');
        });
    }
};
