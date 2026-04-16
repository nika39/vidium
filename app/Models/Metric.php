<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Metric extends Model
{
    /** @use HasFactory<\Database\Factories\MetricFactory> */
    use HasFactory, Prunable;

    public $timestamps = false;

    protected $fillable = [
        'site_id',
        'p2p_bytes',
        'http_bytes',
        'recorded_at',
        'browser',
        'os',
        'player_version',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function prunable(): Builder
    {
        return static::query()->where('recorded_at', '<', now()->subMonths(3));
    }
}
