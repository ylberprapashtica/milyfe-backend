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
            ['name' => 'memory', 'symbol' => '<<'],
            ['name' => 'describing', 'symbol' => '<'],
            ['name' => 'action', 'symbol' => '0'],
            ['name' => 'planning', 'symbol' => '>'],
            ['name' => 'dreaming', 'symbol' => '>>'],
        ];

        foreach ($types as $type) {
            CaptureType::firstOrCreate(
                ['name' => $type['name']],
                ['symbol' => $type['symbol']]
            );
        }
    }
}
