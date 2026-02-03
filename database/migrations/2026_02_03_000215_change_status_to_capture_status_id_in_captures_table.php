<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\CaptureStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, ensure statuses are seeded
        $this->seedStatuses();

        // Get status mapping
        $statusMap = CaptureStatus::pluck('id', 'name')->toArray();

        // Add temporary column for status_id
        Schema::table('captures', function (Blueprint $table) {
            $table->foreignId('capture_status_id')->nullable()->after('graph_y');
        });

        // Migrate existing status values to IDs
        foreach ($statusMap as $name => $id) {
            DB::table('captures')
                ->where('status', $name)
                ->update(['capture_status_id' => $id]);
        }

        // Set default for any null values (should be 'fleeting')
        $fleetingId = $statusMap['fleeting'] ?? null;
        if ($fleetingId) {
            DB::table('captures')
                ->whereNull('capture_status_id')
                ->update(['capture_status_id' => $fleetingId]);
        }

        // Make capture_status_id not nullable and add foreign key
        Schema::table('captures', function (Blueprint $table) use ($fleetingId) {
            $table->foreignId('capture_status_id')->nullable(false)->default($fleetingId)->change();
            $table->foreign('capture_status_id')->references('id')->on('capture_statuses')->onDelete('restrict');
            $table->index('capture_status_id');
        });

        // Drop old status column and constraint
        Schema::table('captures', function (Blueprint $table) {
            DB::statement('ALTER TABLE captures DROP CONSTRAINT IF EXISTS captures_status_check');
            $table->dropColumn('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back status column
        Schema::table('captures', function (Blueprint $table) {
            $table->string('status', 20)->default('fleeting')->after('graph_y');
        });

        // Get status mapping
        $statusMap = CaptureStatus::pluck('name', 'id')->toArray();

        // Migrate IDs back to status strings
        foreach ($statusMap as $id => $name) {
            DB::table('captures')
                ->where('capture_status_id', $id)
                ->update(['status' => $name]);
        }

        // Add constraint
        DB::statement("ALTER TABLE captures ADD CONSTRAINT captures_status_check 
            CHECK (status IN ('fleeting', 'reviewed', 'organized', 'implemented', 'forgotten', 'deleted'))");

        // Drop foreign key and column
        Schema::table('captures', function (Blueprint $table) {
            $table->dropForeign(['capture_status_id']);
            $table->dropIndex(['capture_status_id']);
            $table->dropColumn('capture_status_id');
        });
    }

    /**
     * Seed statuses if they don't exist
     */
    private function seedStatuses(): void
    {
        $statuses = [
            ['name' => 'fleeting', 'color' => '#ffc107'],
            ['name' => 'reviewed', 'color' => '#17a2b8'],
            ['name' => 'organized', 'color' => '#28a745'],
            ['name' => 'implemented', 'color' => '#9c27b0'],
            ['name' => 'forgotten', 'color' => '#dc3545'],
            ['name' => 'deleted', 'color' => '#6c757d'],
        ];

        foreach ($statuses as $status) {
            DB::table('capture_statuses')->updateOrInsert(
                ['name' => $status['name']],
                ['color' => $status['color'], 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
};
