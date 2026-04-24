<?php

namespace App\Services;

use App\Models\Site;

class MetricStatsService
{
    /**
     * Get traffic stats for a site.
     *
     * @return array{
     *     domain: string,
     *     total_p2p: string,
     *     total_http: string,
     *     total_traffic: string,
     *     p2p_ratio: string,
     *     http_ratio: string,
     *     os_breakdown: array<string, string>,
     *     daily_breakdown: array<string, string>,
     *     hourly_breakdown: array<string, string>
     * }
     */
    public function getStats(Site $site): array
    {
        $totals = $site->metrics()
            ->where('recorded_at', '>=', now()->subDays(30))
            ->selectRaw('COALESCE(SUM(p2p_bytes), 0) as total_p2p_bytes, COALESCE(SUM(http_bytes), 0) as total_http_bytes')
            ->first();

        $totalP2pBytes = (int) $totals->total_p2p_bytes;
        $totalHttpBytes = (int) $totals->total_http_bytes;
        $totalBytes = $totalP2pBytes + $totalHttpBytes;

        $p2pPercentage = $totalBytes > 0 ? round(($totalP2pBytes / $totalBytes) * 100, 2) : 0;
        $httpPercentage = $totalBytes > 0 ? round(($totalHttpBytes / $totalBytes) * 100, 2) : 0;

        return [
            'domain' => $site->domain,
            'total_p2p' => $this->formatBytes($totalP2pBytes),
            'total_http' => $this->formatBytes($totalHttpBytes),
            'total_traffic' => $this->formatBytes($totalBytes),
            'p2p_ratio' => $p2pPercentage . '%',
            'http_ratio' => $httpPercentage . '%',
            'os_breakdown' => $this->getOsBreakdown($site),
            'daily_breakdown' => $this->getDailyBreakdown($site),
            'hourly_breakdown' => $this->getHourlyBreakdown($site),
        ];
    }

    /**
     * Get P2P percentage breakdown by operating system.
     *
     * @return array<string, string>
     */
    private function getOsBreakdown(Site $site): array
    {
        $allowedOs = ['Windows', 'macOS', 'Linux', 'Android', 'iOS'];

        $osTotals = $site->metrics()
            ->where('recorded_at', '>=', now()->subHours(24))
            ->whereIn('os', $allowedOs)
            ->selectRaw('os, COALESCE(SUM(p2p_bytes), 0) as total_p2p_bytes, COALESCE(SUM(http_bytes), 0) as total_http_bytes')
            ->groupBy('os')
            ->get();

        return $osTotals
            ->mapWithKeys(function ($row) {
                $p2p = (int) $row->total_p2p_bytes;
                $total = $p2p + (int) $row->total_http_bytes;

                return [$row->os => $total > 0 ? round(($p2p / $total) * 100, 2) : 0];
            })
            ->sortDesc()
            ->map(fn($percentage) => $percentage . '%')
            ->all();
    }

    /**
     * Get P2P traffic percentage breakdown by hour.
     *
     * @return array<string, string>
     */
    private function getHourlyBreakdown(Site $site): array
    {
        $isSqlite = $site->getConnection()->getDriverName() === 'sqlite';

        $hourLabel = $isSqlite ? "strftime('%d.%m %H:00', recorded_at)" : 'DATE_FORMAT(recorded_at, "%d.%m %H:00")';
        $fullHour = $isSqlite ? "strftime('%Y-%m-%d %H:00', recorded_at)" : 'DATE_FORMAT(recorded_at, "%Y-%m-%d %H:00")';

        $hourlyTotals = $site->metrics()
            ->where('recorded_at', '>=', now()->subHours(36))
            ->selectRaw("{$hourLabel} as hour_label, {$fullHour} as full_hour, COALESCE(SUM(p2p_bytes), 0) as total_p2p_bytes, COALESCE(SUM(http_bytes), 0) as total_http_bytes")
            ->groupBy('full_hour', 'hour_label')
            ->orderBy('full_hour')
            ->get();

        return $hourlyTotals
            ->mapWithKeys(function ($row) {
                $p2p = (int) $row->total_p2p_bytes;
                $total = $p2p + (int) $row->total_http_bytes;

                return [$row->hour_label => $total > 0 ? round(($p2p / $total) * 100, 2) : 0];
            })
            ->map(fn($percentage) => $percentage . '%')
            ->all();
    }

    /**
     * Get P2P traffic percentage breakdown by day (last 30 days).
     *
     * @return array<string, string>
     */
    private function getDailyBreakdown(Site $site): array
    {
        $isSqlite = $site->getConnection()->getDriverName() === 'sqlite';

        $dayLabel = $isSqlite ? "strftime('%d.%m', recorded_at)" : 'DATE_FORMAT(recorded_at, "%d.%m")';
        $fullDay = $isSqlite ? "strftime('%Y-%m-%d', recorded_at)" : 'DATE_FORMAT(recorded_at, "%Y-%m-%d")';

        $dailyTotals = $site->metrics()
            ->where('recorded_at', '>=', now()->subDays(30))
            ->selectRaw("{$dayLabel} as day_label, {$fullDay} as full_day, COALESCE(SUM(p2p_bytes), 0) as total_p2p_bytes, COALESCE(SUM(http_bytes), 0) as total_http_bytes")
            ->groupBy('full_day', 'day_label')
            ->orderBy('full_day')
            ->get();

        return $dailyTotals
            ->mapWithKeys(function ($row) {
                $p2p = (int) $row->total_p2p_bytes;
                $total = $p2p + (int) $row->total_http_bytes;

                return [$row->day_label => $total > 0 ? round(($p2p / $total) * 100, 2) : 0];
            })
            ->map(fn($percentage) => $percentage . '%')
            ->all();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}
