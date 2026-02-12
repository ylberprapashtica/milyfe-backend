<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $captures = DB::table('captures')
            ->whereNotNull('tags')
            ->whereRaw("tags::text NOT IN ('[]', 'null')")
            ->get();

        foreach ($captures as $capture) {
            $tags = json_decode($capture->tags, true);
            if (!is_array($tags)) {
                continue;
            }

            foreach ($tags as $tagName) {
                if (!is_string($tagName) || trim($tagName) === '') {
                    continue;
                }

                $tagName = strtolower(trim(mb_substr($tagName, 0, 50)));

                $tag = DB::table('tags')
                    ->where('user_id', $capture->user_id)
                    ->where('name', $tagName)
                    ->first();

                if (!$tag) {
                    $tagId = DB::table('tags')->insertGetId([
                        'user_id' => $capture->user_id,
                        'name' => $tagName,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $tagId = $tag->id;
                }

                DB::table('capture_tag')->insertOrIgnore([
                    'capture_id' => $capture->id,
                    'tag_id' => $tagId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('captures', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('captures', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('slug');
        });

        $captures = DB::table('captures')->get();
        foreach ($captures as $capture) {
            $tagNames = DB::table('capture_tag')
                ->join('tags', 'capture_tag.tag_id', '=', 'tags.id')
                ->where('capture_tag.capture_id', $capture->id)
                ->pluck('tags.name')
                ->toArray();

            DB::table('captures')
                ->where('id', $capture->id)
                ->update(['tags' => json_encode($tagNames)]);
        }
    }
};
