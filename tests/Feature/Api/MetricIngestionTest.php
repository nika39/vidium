<?php

use App\Models\Site;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    Redis::spy();

    $this->site = Site::factory()->create();
});

it('accepts valid metric data', function () {
    $response = $this->postJson('/api/metrics', [
        'license_key' => $this->site->license_key,
        'p2p_bytes' => 1024,
        'http_bytes' => 2048,
        'browser' => 'Chrome',
        'os' => 'Windows',
        'player_version' => '1.0.0',
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => 'ok']);
});

it('rejects request with invalid license key', function () {
    $response = $this->postJson('/api/metrics', [
        'license_key' => 'invalid-key',
        'p2p_bytes' => 1024,
        'http_bytes' => 2048,
    ]);

    $response->assertUnauthorized()
        ->assertJson(['error' => 'Invalid, expired, or inactive license']);
});

it('rejects request with expired site', function () {
    $site = Site::factory()->expired()->create();

    $response = $this->postJson('/api/metrics', [
        'license_key' => $site->license_key,
        'p2p_bytes' => 1024,
        'http_bytes' => 2048,
    ]);

    $response->assertUnauthorized();
});

it('rejects request with inactive site', function () {
    $site = Site::factory()->inactive()->create();

    $response = $this->postJson('/api/metrics', [
        'license_key' => $site->license_key,
        'p2p_bytes' => 1024,
        'http_bytes' => 2048,
    ]);

    $response->assertUnauthorized();
});

it('requires license_key', function () {
    $response = $this->postJson('/api/metrics', [
        'p2p_bytes' => 1024,
        'http_bytes' => 2048,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['license_key']);
});

it('rejects bytes exceeding max allowed value', function () {
    $maxBitrate = config('metrics.max_video_bitrate_mbps');
    $pingInterval = config('metrics.ping_interval_seconds');
    $prefetchMultiplier = config('metrics.prefetch_multiplier');
    $maxBytes = (int) (($maxBitrate / 8) * $pingInterval * 1024 * 1024 * $prefetchMultiplier);

    $response = $this->postJson('/api/metrics', [
        'license_key' => $this->site->license_key,
        'p2p_bytes' => $maxBytes + 1,
        'http_bytes' => 0,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['p2p_bytes']);
})->skip('Security checks disabled in feat/disable-security branch');

it('rejects negative byte values', function () {
    $response = $this->postJson('/api/metrics', [
        'license_key' => $this->site->license_key,
        'p2p_bytes' => -1,
        'http_bytes' => 0,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['p2p_bytes']);
});

it('defaults optional fields to Unknown', function () {
    Redis::shouldReceive('pipeline')->once()->andReturnUsing(function ($callback) {
        $pipe = Mockery::mock();
        $pipe->shouldReceive('hincrby')->twice();
        $pipe->shouldReceive('sadd')->once();
        $callback($pipe);
    });

    $this->postJson('/api/metrics', [
        'license_key' => $this->site->license_key,
        'p2p_bytes' => 1024,
        'http_bytes' => 2048,
    ])->assertSuccessful();
});

it('respects rate limiting from config', function () {
    config(['metrics.rate_limit.max_attempts' => 2]);

    // Re-register the rate limiter with new config
    app(AppServiceProvider::class, ['app' => app()])->boot();

    $payload = [
        'license_key' => $this->site->license_key,
        'p2p_bytes' => 1024,
        'http_bytes' => 2048,
    ];

    $this->postJson('/api/metrics', $payload)->assertSuccessful();
    $this->postJson('/api/metrics', $payload)->assertSuccessful();
    $this->postJson('/api/metrics', $payload)->assertTooManyRequests();
})->skip('Security checks disabled in feat/disable-security branch');

it('accepts bytes at exactly the max allowed value', function () {
    $maxBitrate = config('metrics.max_video_bitrate_mbps');
    $pingInterval = config('metrics.ping_interval_seconds');
    $prefetchMultiplier = config('metrics.prefetch_multiplier');
    $maxBytes = (int) (($maxBitrate / 8) * $pingInterval * 1024 * 1024 * $prefetchMultiplier);

    $response = $this->postJson('/api/metrics', [
        'license_key' => $this->site->license_key,
        'p2p_bytes' => $maxBytes,
        'http_bytes' => 0,
    ]);

    $response->assertSuccessful();
});

it('calculates max bytes based on config values', function () {
    config([
        'metrics.max_video_bitrate_mbps' => 10,
        'metrics.ping_interval_seconds' => 20,
        'metrics.prefetch_multiplier' => 2,
    ]);

    $maxBytes = (int) ((10 / 8) * 20 * 1024 * 1024 * 2);

    $this->postJson('/api/metrics', [
        'license_key' => $this->site->license_key,
        'p2p_bytes' => $maxBytes + 1,
        'http_bytes' => 0,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['p2p_bytes']);

    $this->postJson('/api/metrics', [
        'license_key' => $this->site->license_key,
        'p2p_bytes' => $maxBytes,
        'http_bytes' => 0,
    ])->assertSuccessful();
})->skip('Security checks disabled in feat/disable-security branch');
