<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('captures', function (Blueprint $table) {
            $table->string('status', 20)->default('fleeting')->after('graph_y');
        });

        // Add constraint to ensure only valid status values
        DB::statement("ALTER TABLE captures ADD CONSTRAINT captures_status_check 
            CHECK (status IN ('fleeting', 'reviewed', 'organized', 'implemented', 'forgotten', 'deleted'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('captures', function (Blueprint $table) {
            DB::statement('ALTER TABLE captures DROP CONSTRAINT IF EXISTS captures_status_check');
            $table->dropColumn('status');
        });
    }
};
