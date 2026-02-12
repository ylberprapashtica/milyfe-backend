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
            $table->text('voice_audio')->nullable()->after('sketch_image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('captures', function (Blueprint $table) {
            $table->dropColumn('voice_audio');
        });
    }
};
