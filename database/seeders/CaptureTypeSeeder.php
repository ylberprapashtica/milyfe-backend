<?php

namespace Database\Seeders;

use App\Models\CaptureType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CaptureTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'memory', 'symbol' => '<<', 'description' => 'Past experiences, recollections, things remembered'],
            ['name' => 'describing', 'symbol' => '<', 'description' => 'Current observations, what is happening now'],
            ['name' => 'action', 'symbol' => '0', 'description' => 'Tasks, things to do, immediate actions'],
            ['name' => 'planning', 'symbol' => '>', 'description' => 'Future plans, intentions, how to achieve goals'],
            ['name' => 'dreaming', 'symbol' => '>>', 'description' => 'Aspirations, big ideas, long-term visions'],
            ['name' => 'eureka', 'symbol' => '!', 'description' => 'Insights, breakthroughs, sudden realizations'],
        ];

        foreach ($types as $type) {
            CaptureType::updateOrCreate(
                ['name' => $type['name']],
                ['symbol' => $type['symbol'], 'description' => $type['description']]
            );
        }
    }
}
