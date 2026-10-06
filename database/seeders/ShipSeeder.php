<?php

namespace Database\Seeders;

use App\Models\Ship;
use Illuminate\Database\Seeder;

class ShipSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = storage_path('app/private/templates/survey_template.json');

        if (file_exists($jsonPath)) {
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

        $this->enrichParticulars();
    }

    /**
     * Lengkapi ship particulars kapal benchmark (dipakai BAB II laporan).
     * Hanya mengisi kolom yang masih null — aman dijalankan ulang dan tidak
     * menimpa data yang sudah diisi manual. Nilai deterministik per kapal.
     */
    protected function enrichParticulars(): void
    {
        $builders = [
            'PT. Dumas Tanjung Perak Shipyards',
            'PT. Dok & Perkapalan Surabaya',
            'PT. Adiluhung Saranasegara Indonesia',
            'PT. Daya Radar Utama',
            'PT. Karimun Sembawang Shipyard',
            'PT. Caputra Mitra Sejati Shipyard',
        ];
        $ports = ['Jakarta', 'Surabaya', 'Merak', 'Bakauheni', 'Makassar', 'Batam', 'Balikpapan'];
        $mainEngines = ['Yanmar 6EY26W', 'Niigata 6NSD-M', 'Daihatsu 6DK-20', 'MAN B&W 9L27/38', 'Wartsila 6L20'];
        $auxEngines = ['Yanmar 6HAL2-WN', 'Cummins 6BTA5.9', 'Caterpillar C4.4', 'Perkins 1104D'];

        $enriched = 0;

        Ship::query()->orderBy('id')->each(function (Ship $ship) use ($builders, $ports, $mainEngines, $auxEngines, &$enriched) {
            $fake = fake();
            $fake->seed($ship->id * 7919);

            $gt = $fake->numberBetween(1000, 6000);
            $loa = $fake->randomFloat(2, 45, 85);

            $acronym = implode('', array_map(
                fn ($word) => $word[0],
                preg_split('/\s+/', trim((string) preg_replace('/[^A-Za-z0-9 ]/', '', str_replace('KMP.', '', (string) $ship->name)))) ?: ['X']
            ));
            $code = strtoupper(substr($acronym, 0, 6)).'-'.($ship->year_built ?? 'XX');
            if (Ship::where('code', $code)->whereKeyNot($ship->id)->exists()) {
                $code .= '-'.$ship->id;
            }

            $attrs = [
                'code' => $code,
                'imo_number' => '9'.$fake->numerify('######'),
                'ship_type' => 'Ferry Ro-Ro',
                'flag' => 'Indonesia',
                'gross_tonnage' => (string) $gt,
                'net_tonnage' => (string) (int) round($gt * 0.63),
                'loa' => number_format($loa, 2),
                'lpp' => number_format($loa * 0.88, 2),
                'breadth' => number_format($fake->randomFloat(2, 12, 16), 2),
                'depth' => number_format($fake->randomFloat(2, 3.2, 4.5), 2),
                'draft' => number_format($fake->randomFloat(2, 2.4, 3.4), 2),
                'dwt' => (string) $fake->numberBetween(300, 900),
                'builder' => $fake->randomElement($builders),
                'port_of_registry' => $fake->randomElement($ports),
                'hull_material' => 'Steel',
                'class_name' => 'BKI',
                'class_notations' => 'A100 — RORO Passenger Ship',
                'call_sign' => 'YB'.$fake->lexify('??'),
                'main_engine' => $fake->randomElement($mainEngines),
                'main_engine_power' => '2 x '.$fake->numberBetween(5, 12).'00 kW',
                'aux_engine' => $fake->randomElement($auxEngines),
                'aux_engine_power' => '3 x '.$fake->numberBetween(1, 3).'25 kVA',
                'owner' => 'PT. ASDP Indonesia Ferry (Persero)',
                'operator' => 'PT. ASDP Indonesia Ferry (Persero)',
            ];

            $dirty = false;

            // Normalisasi: unit "m" adalah urusan display, bukan data mentah
            foreach (['loa', 'lpp', 'breadth', 'depth', 'draft'] as $dim) {
                if (is_string($ship->{$dim}) && str_ends_with($ship->{$dim}, ' m')) {
                    $ship->{$dim} = rtrim(substr($ship->{$dim}, 0, -2));
                    $dirty = true;
                }
            }

            foreach ($attrs as $key => $value) {
                if ($ship->{$key} === null) {
                    $ship->{$key} = $value;
                    $dirty = true;
                }
            }

            if ($dirty) {
                $ship->save();
                $enriched++;
            }
        });

        $this->command->info("Ship particulars enriched: {$enriched} ships");
    }
}
