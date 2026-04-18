<?php

namespace App\Services;

use App\Models\Metric;
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
                'recorded_at' => $parts[2],
                'browser' => $parts[3],
                'os' => $parts[4],
                'player_version' => $parts[5],
            ];
        }

        if (! empty($recordsToUpsert)) {
            DB::transaction(function () use ($recordsToUpsert) {
                foreach (array_chunk($recordsToUpsert, 1000) as $chunk) {
                    Metric::upsert(
                        $chunk,
                        ['site_id', 'recorded_at', 'browser', 'os', 'player_version'],
                        [
                            'p2p_bytes' => DB::raw('metrics.p2p_bytes + VALUES(p2p_bytes)'),
                            'http_bytes' => DB::raw('metrics.http_bytes + VALUES(http_bytes)'),
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
