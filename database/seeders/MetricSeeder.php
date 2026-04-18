<?php

namespace Database\Seeders;

use App\Models\Site;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MetricSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $siteIds = Site::pluck('id')->toArray();

        if (empty($siteIds)) {
            $this->command->warn('No sites found. Please create a Site first.');

            return;
        }

        $this->command->info('Starting mass data generation for Metrics...');

        $totalRecords = 100000;
        $batchSize = 5000;

        $browsers = ['Chrome', 'Safari', 'Firefox', 'Edge', 'Opera'];
        $oses = ['Windows', 'iOS', 'Android', 'macOS', 'Linux'];
        $playerVersions = ['v1.5.20', 'v1.5.21', 'v1.6.0'];

        $data = [];

        for ($i = 1; $i <= $totalRecords; $i++) {
            $randomDaysAgo = rand(0, 90);
            $randomHour = rand(0, 23);

            $dateHour = Carbon::now()
                ->subDays($randomDaysAgo)
                ->subHours($randomHour)
                ->startOfHour()
                ->format('Y-m-d H:i:s');

            $data[] = [
                'site_id' => $siteIds[array_rand($siteIds)],
                'recorded_at' => $dateHour,
                'browser' => $browsers[array_rand($browsers)],
                'os' => $oses[array_rand($oses)],
                'player_version' => $playerVersions[array_rand($playerVersions)],
                'p2p_bytes' => rand(100000000, 5000000000),
                'http_bytes' => rand(5000000, 200000000),
            ];

            if (count($data) >= $batchSize) {
                DB::table('metrics')->insertOrIgnore($data);
                $data = [];
                $this->command->info("Inserted {$i} records so far...");
            }
        }

        if (! empty($data)) {
            DB::table('metrics')->insertOrIgnore($data);
        }

        $this->command->info("Successfully seeded {$totalRecords} metric records!");
    }
}
