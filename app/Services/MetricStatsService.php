<?php

namespace App\Services;

use App\Models\Site;

class MetricStatsService
{
    /**
     * Get traffic stats for a site.
     *
     * @return array{domain: string, total_p2p: string, total_http: string, total_traffic: string, p2p_ratio: string, http_ratio: string}
     */
    public function getStats(Site $site): array
    {
        $totals = $site->metrics()
            ->where('recorded_at', '>=', now()->subHours(24))
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
            'p2p_ratio' => $p2pPercentage.'%',
            'http_ratio' => $httpPercentage.'%',
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 2).' '.$units[$power];
    }
}
