<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class MetricIngestionService
{
    public function process(array $data): bool
    {
        $siteId = Cache::remember('site_license:'.$data['license_key'], 3600, function () use ($data) {
            $site = Site::where('license_key', $data['license_key'])->first();

            return $site?->isValid() ? $site->id : null;
        });

        if (! $siteId) {
            return false;
        }

        $hour = now()->format('Y-m-d_H');

        $redisKey = sprintf(
            'metrics:%s:%s:%s:%s:%s',
            $siteId,
            $hour,
            $data['browser'],
            $data['os'],
            $data['player_version']
        );

        Redis::pipeline(function ($pipe) use ($redisKey, $data) {
            $pipe->hincrby($redisKey, 'p2p_bytes', $data['p2p_bytes']);
            $pipe->hincrby($redisKey, 'http_bytes', $data['http_bytes']);

            $pipe->sadd('active_metric_keys', $redisKey);
        });

        return true;
    }
}
