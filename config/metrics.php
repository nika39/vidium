<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum Video Bitrate (Mbps)
    |--------------------------------------------------------------------------
    |
    | The maximum expected video bitrate in megabits per second. This value is
    | used to calculate the upper bound for bytes that a single ping can
    | report, preventing clients from submitting unrealistic data.
    |
    */

    'max_video_bitrate_mbps' => (int) env('METRICS_MAX_BITRATE_MBPS', 35),

    /*
    |--------------------------------------------------------------------------
    | Ping Interval (Seconds)
    |--------------------------------------------------------------------------
    |
    | How often the client sends metric pings to the server. Combined with the
    | max bitrate, this determines the maximum allowed bytes per request.
    | Adjust this when changing the client-side reporting interval.
    |
    */

    'ping_interval_seconds' => (int) env('METRICS_PING_INTERVAL', 30),

    /*
    |--------------------------------------------------------------------------
    | Prefetch Multiplier
    |--------------------------------------------------------------------------
    |
    | P2P clients opportunistically download future segments when peers are
    | available, causing reported bytes per ping to exceed the real-time
    | video bitrate. This multiplier accounts for that burst behavior.
    |
    */

    'prefetch_multiplier' => (int) env('METRICS_PREFETCH_MULTIPLIER', 4),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Controls the rate limit for the metric ingestion endpoint. max_attempts
    | defines how many requests are allowed within the decay_seconds window,
    | keyed by the client's IP address.
    |
    */

    'rate_limit' => [
        'max_attempts' => (int) env('METRICS_RATE_LIMIT_MAX', 10),
    ],

];
