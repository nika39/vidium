<?php

use App\Models\Metric;
use App\Models\Site;

it('returns last 24h traffic stats for a site', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    Metric::factory()->for($site)->create([
        'p2p_bytes' => 3_000_000_000,
        'http_bytes' => 1_000_000_000,
        'recorded_at' => now()->subHours(2),
    ]);

    Metric::factory()->for($site)->create([
        'p2p_bytes' => 1_000_000_000,
        'http_bytes' => 1_000_000_000,
        'recorded_at' => now(),
    ]);

    $response = $this->getJson("/api/metrics/{$site->id}");

    $response->assertSuccessful()
        ->assertJson([
            'domain' => 'example.com',
            'p2p_ratio' => '66.67%',
            'http_ratio' => '33.33%',
        ]);
});

it('excludes metrics older than 24 hours', function () {
    $site = Site::factory()->create(['domain' => 'example.com']);

    Metric::factory()->for($site)->create([
        'p2p_bytes' => 5_000_000,
        'http_bytes' => 5_000_000,
        'recorded_at' => now()->subHours(25),
    ]);

    Metric::factory()->for($site)->create([
        'p2p_bytes' => 1_000_000,
        'http_bytes' => 0,
        'recorded_at' => now(),
    ]);

    $response = $this->getJson("/api/metrics/{$site->id}");

    $response->assertSuccessful()
        ->assertJson([
            'p2p_ratio' => '100%',
            'http_ratio' => '0%',
        ]);
});

it('returns 404 for non-existent site', function () {
    $this->getJson('/api/metrics/999')
        ->assertNotFound();
});

it('returns zero stats when site has no metrics', function () {
    $site = Site::factory()->create(['domain' => 'empty.com']);

    $response = $this->getJson("/api/metrics/{$site->id}");

    $response->assertSuccessful()
        ->assertJson([
            'domain' => 'empty.com',
            'total_p2p' => '0 B',
            'total_http' => '0 B',
            'total_traffic' => '0 B',
            'p2p_ratio' => '0%',
            'http_ratio' => '0%',
        ]);
});
