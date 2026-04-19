<?php

namespace Database\Factories;

use App\Models\Metric;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Metric>
 */
class MetricFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'p2p_bytes' => fake()->numberBetween(0, 10_000_000),
            'http_bytes' => fake()->numberBetween(0, 10_000_000),
            'recorded_at' => now()->format('Y-m-d_H'),
            'browser' => fake()->randomElement(['Chrome', 'Firefox', 'Safari']),
            'os' => fake()->randomElement(['Windows', 'macOS', 'Linux']),
            'player_version' => fake()->randomElement(['1.0', '1.1', '1.2']),
        ];
    }
}
