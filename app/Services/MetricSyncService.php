<?php

namespace App\Services;

use App\Models\Metric;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class MetricSyncService
{
    public function sync(): int
    {
        $keys = Redis::smembers('active_metric_keys');

        if (empty($keys)) {
            return 0;
        }

        $redisData = Redis::pipeline(function ($pipe) use ($keys) {
            foreach ($keys as $key) {
                $pipe->hgetall($key);
            }
        });

        $recordsToUpsert = [];

        foreach ($keys as $index => $key) {
            $data = $redisData[$index];

            if (empty($data)) {
                continue;
            }

            $parts = explode(':', $key);

            if (count($parts) !== 6) {
                Log::warning("Invalid metric key format: {$key}");

                continue;
            }

            $recordsToUpsert[] = [
                'site_id' => (int) $parts[1],
                'p2p_bytes' => (int) ($data['p2p_bytes'] ?? 0),
                'http_bytes' => (int) ($data['http_bytes'] ?? 0),
                'recorded_at' => str_replace('_', ' ', $parts[2]).':00:00',
                'browser' => $parts[3],
                'os' => $parts[4],
                'player_version' => $parts[5],
            ];
        }

        if (! empty($recordsToUpsert)) {
            $siteIds = array_unique(array_column($recordsToUpsert, 'site_id'));
            $validSiteIds = Site::query()->whereIn('id', $siteIds)->pluck('id')->all();

            foreach (array_diff($siteIds, $validSiteIds) as $orphanedSiteId) {
                Log::warning("Skipping metrics for non-existent site ID: {$orphanedSiteId}");
            }

            $recordsToUpsert = array_values(array_filter(
                $recordsToUpsert,
                fn (array $record) => in_array($record['site_id'], $validSiteIds)
            ));
        }

        if (! empty($recordsToUpsert)) {
            DB::transaction(function () use ($recordsToUpsert) {
                $isMysql = DB::getDriverName() === 'mysql';

                foreach (array_chunk($recordsToUpsert, 1000) as $chunk) {
                    Metric::upsert(
                        $chunk,
                        ['site_id', 'recorded_at', 'browser', 'os', 'player_version'],
                        [
                            'p2p_bytes' => DB::raw($isMysql
                                ? 'metrics.p2p_bytes + VALUES(p2p_bytes)'
                                : 'metrics.p2p_bytes + excluded.p2p_bytes'),
                            'http_bytes' => DB::raw($isMysql
                                ? 'metrics.http_bytes + VALUES(http_bytes)'
                                : 'metrics.http_bytes + excluded.http_bytes'),
                        ]
                    );
                }
            });
        }

        Redis::pipeline(function ($pipe) use ($keys) {
            foreach ($keys as $key) {
                $pipe->srem('active_metric_keys', $key);
                $pipe->del($key);
            }
        });

        return count($recordsToUpsert);
    }
}
