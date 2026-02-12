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
            $table->foreignId('project_id')->nullable()->after('graph_y')->constrained()->onDelete('set null');
            $table->decimal('project_x', 10, 2)->nullable()->after('project_id');
            $table->decimal('project_y', 10, 2)->nullable()->after('project_x');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('captures', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn(['project_id', 'project_x', 'project_y']);
        });
    }
};
