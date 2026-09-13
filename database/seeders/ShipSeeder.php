<?php

namespace Database\Seeders;

use App\Models\Ship;
use Illuminate\Database\Seeder;

class ShipSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = storage_path('app/private/templates/survey_template.json');

        if (! file_exists($jsonPath)) {
            return;
        }

        $data = json_decode(file_get_contents($jsonPath), true);

        foreach ($data['benchmark_ships'] as $ship) {
            Ship::firstOrCreate(
                ['name' => $ship['name']],
                [
                    'year_built' => $ship['year_built'],
                    'status' => 'active',
                ]
            );
        }

        $this->command->info('Ships seeded: '.count($data['benchmark_ships']).' benchmark ships');
    }
}
