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
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('graph_x', 10, 2)->nullable()->after('description');
            $table->decimal('graph_y', 10, 2)->nullable()->after('graph_x');
            $table->decimal('graph_width', 10, 2)->nullable()->after('graph_y');
            $table->decimal('graph_height', 10, 2)->nullable()->after('graph_width');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['graph_x', 'graph_y', 'graph_width', 'graph_height']);
        });
    }
};
