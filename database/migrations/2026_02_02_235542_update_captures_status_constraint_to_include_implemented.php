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
        // Drop the existing constraint
        DB::statement('ALTER TABLE captures DROP CONSTRAINT IF EXISTS captures_status_check');
        
        // Add the updated constraint with 'implemented' status
        DB::statement("ALTER TABLE captures ADD CONSTRAINT captures_status_check 
            CHECK (status IN ('fleeting', 'reviewed', 'organized', 'implemented', 'forgotten', 'deleted'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the updated constraint
        DB::statement('ALTER TABLE captures DROP CONSTRAINT IF EXISTS captures_status_check');
        
        // Restore the original constraint without 'implemented'
        DB::statement("ALTER TABLE captures ADD CONSTRAINT captures_status_check 
            CHECK (status IN ('fleeting', 'reviewed', 'organized', 'forgotten', 'deleted'))");
    }
};
