<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CaptureStatus;

class CaptureStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
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
            CaptureStatus::updateOrCreate(
                ['name' => $status['name']],
                ['color' => $status['color']]
            );
        }
    }
}
