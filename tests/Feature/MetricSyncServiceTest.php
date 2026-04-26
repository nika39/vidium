<?php

use App\Models\Metric;
use App\Models\Site;
use App\Services\MetricSyncService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

it('syncs metrics only for existing sites and skips orphaned site IDs', function () {
    $site = Site::factory()->create();

    $validKey = "metric:{$site->id}:2026-04-18_19:Chrome:Windows:1.0.0";
    $orphanedKey = 'metric:99999:2026-04-18_19:Unknown:Unknown:Unknown';

    Redis::shouldReceive('smembers')
        ->once()
        ->with('active_metric_keys')
        ->andReturn([$validKey, $orphanedKey]);

    Redis::shouldReceive('pipeline')
        ->once()
        ->with(Mockery::type('Closure'))
        ->andReturn([
            ['p2p_bytes' => '1000', 'http_bytes' => '500'],
            ['p2p_bytes' => '26214400', 'http_bytes' => '0'],
        ]);

    Redis::shouldReceive('pipeline')
        ->once()
        ->with(Mockery::type('Closure'))
        ->andReturn([null, null, null, null]);

    Log::shouldReceive('info');
    Log::shouldReceive('warning')
        ->once()
        ->with('Skipping metrics for non-existent site ID: 99999');

    $synced = app(MetricSyncService::class)->sync();

    expect($synced)->toBe(1);
    expect(Metric::count())->toBe(1);
    expect(Metric::first()->site_id)->toBe($site->id);
});

it('returns zero when there are no active metric keys', function () {
    Redis::shouldReceive('smembers')
        ->once()
        ->with('active_metric_keys')
        ->andReturn([]);

    Log::shouldReceive('info');

    $synced = app(MetricSyncService::class)->sync();

    expect($synced)->toBe(0);
    expect(Metric::count())->toBe(0);
});
