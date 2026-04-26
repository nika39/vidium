import Redis from 'ioredis';
import mysql from 'mysql2/promise';
import type { Pool, RowDataPacket } from 'mysql2/promise';

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------

const PORT = Number(process.env.PORT) || 3000;
const REDIS_PREFIX = process.env.REDIS_PREFIX || 'vidium-database-';

const redisPassword = process.env.REDIS_PASSWORD;

const redisConfig = {
    host: process.env.REDIS_HOST || '127.0.0.1',
    port: Number(process.env.REDIS_PORT) || 6379,
    password:
        redisPassword === 'null' || !redisPassword ? undefined : redisPassword,
    db: Number(process.env.REDIS_DB) || 0,
    maxRetriesPerRequest: 3,
    lazyConnect: true,
};

const mysqlConfig: mysql.PoolOptions = {
    host: process.env.MYSQL_HOST || '127.0.0.1',
    port: Number(process.env.MYSQL_PORT) || 3306,
    database: process.env.MYSQL_DATABASE || 'vidium',
    user: process.env.MYSQL_USER || 'sail',
    password: process.env.MYSQL_PASSWORD || '',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0,
    enableKeepAlive: true,
    keepAliveInitialDelay: 10_000,
};

// ---------------------------------------------------------------------------
// Connections
// ---------------------------------------------------------------------------

const redis = new Redis(redisConfig);
let db: Pool = mysql.createPool(mysqlConfig);
let isRecreatingPool = false;
let lastRecreateTime = 0;

async function executeQuery<T>(sql: string, params: any[]): Promise<T> {
    while (isRecreatingPool) {
        await new Promise((resolve) => setTimeout(resolve, 50));
    }

    try {
        const [rows] = await db.execute(sql, params);

        return rows as T;
    } catch (error: any) {
        const isFatal =
            error.code === 'ECONNREFUSED' ||
            error.code === 'PROTOCOL_CONNECTION_LOST' ||
            error.code === 'ECONNRESET' ||
            error.fatal;

        if (isFatal) {
            if (!isRecreatingPool) {
                const now = Date.now();

                if (now - lastRecreateTime > 5000) {
                    isRecreatingPool = true;
                    console.warn(
                        `[metrics] MySQL error (${error.code}). Recreating pool...`,
                    );
                    db.end().catch(() => {});
                    db = mysql.createPool(mysqlConfig);
                    lastRecreateTime = Date.now();
                    isRecreatingPool = false;
                }
            }

            while (isRecreatingPool) {
                await new Promise((resolve) => setTimeout(resolve, 50));
            }

            const [rows] = await db.execute(sql, params);

            return rows as T;
        }

        throw error;
    }
}

// ---------------------------------------------------------------------------
// In-Memory License Cache (TTL 1 hour)
// ---------------------------------------------------------------------------

interface CacheEntry {
    siteId: number;
    expiresAt: number; // Unix ms
}

const LICENSE_TTL_MS = 60 * 60 * 1000; // 1 hour
const licenseCache = new Map<string, CacheEntry>();

/**
 * Evict stale entries periodically so the Map doesn't grow unbounded.
 * Runs every 5 minutes — lightweight even at millions of entries.
 */
const EVICT_INTERVAL_MS = 5 * 60 * 1000;
setInterval(() => {
    const now = Date.now();

    for (const [key, entry] of licenseCache) {
        if (entry.expiresAt <= now) {
            licenseCache.delete(key);
        }
    }
}, EVICT_INTERVAL_MS);

/**
 * Resolve license_key → site_id.
 * Cache-Aside: check Map first, fall back to MySQL on miss.
 */
async function resolveSiteId(licenseKey: string): Promise<number | null> {
    const now = Date.now();
    const cached = licenseCache.get(licenseKey);

    if (cached && cached.expiresAt > now) {
        return cached.siteId;
    }

    // Cache miss — query MySQL
    const sql = `SELECT id FROM sites
     WHERE license_key = ? AND is_active = 1 AND expires_at > NOW()
     LIMIT 1`;

    const rows = await executeQuery<RowDataPacket[]>(sql, [licenseKey]);

    if (rows.length === 0) {
        // Cache negative result to avoid hammering MySQL on invalid keys
        licenseCache.delete(licenseKey);

        return null;
    }

    const siteId = rows[0].id as number;
    licenseCache.set(licenseKey, {
        siteId,
        expiresAt: now + LICENSE_TTL_MS,
    });

    return siteId;
}

// ---------------------------------------------------------------------------
// Payload Parsing & Validation
// ---------------------------------------------------------------------------

interface MetricPayload {
    license_key: string;
    p2p_bytes: number;
    http_bytes: number;
    browser: string;
    os: string;
    player_version: string;
}

/**
 * Mirrors StoreMetricRequest::prepareForValidation + rules().
 * Accepts either a raw JSON body or a { payload: "<base64>" } envelope.
 */
function parseAndValidate(body: unknown): MetricPayload | null {
    if (typeof body !== 'object' || body === null) {
        return null;
    }

    let data = body as Record<string, unknown>;

    // Base64 envelope — decode and merge
    if (typeof data.payload === 'string') {
        try {
            const decoded = atob(data.payload);
            const parsed = JSON.parse(decoded);

            if (typeof parsed === 'object' && parsed !== null) {
                data = { ...data, ...parsed };
            }
        } catch {
            return null;
        }
    }

    // license_key: required string, max 255
    const licenseKey = data.license_key;

    if (
        typeof licenseKey !== 'string' ||
        licenseKey.length === 0 ||
        licenseKey.length > 255
    ) {
        return null;
    }

    // p2p_bytes / http_bytes: integer >= 0
    const p2pBytes = Number(data.p2p_bytes ?? 0);
    const httpBytes = Number(data.http_bytes ?? 0);

    if (!Number.isInteger(p2pBytes) || p2pBytes < 0) {
        return null;
    }

    if (!Number.isInteger(httpBytes) || httpBytes < 0) {
        return null;
    }

    // browser / os / player_version: nullable string, max 50, default "Unknown"
    const browser = sanitizeField(data.browser, 50);
    const os = sanitizeField(data.os, 50);
    const playerVersion = sanitizeField(data.player_version, 50);

    return {
        license_key: licenseKey,
        p2p_bytes: p2pBytes,
        http_bytes: httpBytes,
        browser,
        os,
        player_version: playerVersion,
    };
}

function sanitizeField(value: unknown, maxLen: number): string {
    if (typeof value === 'string' && value.trim().length > 0) {
        return value.trim().slice(0, maxLen);
    }

    return 'Unknown';
}

// ---------------------------------------------------------------------------
// Redis Ingestion
// ---------------------------------------------------------------------------

/**
 * Push metric data into Redis using the EXACT same structure as
 * Laravel's MetricIngestionService::process().
 *
 * Key structure (unprefixed): metrics:{siteId}:{YYYY-MM-DD_HH}:{browser}:{os}:{player_version}
 *
 * Redis operations (pipelined):
 *   HINCRBY  {prefix}{key}  p2p_bytes   <value>
 *   HINCRBY  {prefix}{key}  http_bytes  <value>
 *   SADD     {prefix}active_metric_keys  {key}   ← member is UNPREFIXED
 *
 * This ensures MetricSyncService::sync() reads via phpredis (which auto-applies
 * the same prefix) and processes the data without any changes.
 */
async function pushToRedis(siteId: number, data: MetricPayload): Promise<void> {
    // Generate hour bucket matching Laravel's now()->format('Y-m-d_H')
    const now = new Date();
    const hour = formatHour(now);

    // Unprefixed key — this is what Laravel stores as the Set member
    const metricKey = `metrics:${siteId}:${hour}:${data.browser}:${data.os}:${data.player_version}`;

    // Prefixed key — the actual hash in Redis
    const prefixedKey = `${REDIS_PREFIX}${metricKey}`;
    const prefixedSetKey = `${REDIS_PREFIX}active_metric_keys`;

    const pipeline = redis.pipeline();
    pipeline.hincrby(prefixedKey, 'p2p_bytes', data.p2p_bytes);
    pipeline.hincrby(prefixedKey, 'http_bytes', data.http_bytes);
    pipeline.sadd(prefixedSetKey, metricKey);
    await pipeline.exec();
}

/**
 * Format a Date as "YYYY-MM-DD_HH" to match PHP's now()->format('Y-m-d_H').
 * Uses UTC-offset aware formatting to match the server timezone.
 */
function formatHour(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    const h = String(date.getHours()).padStart(2, '0');

    return `${y}-${m}-${d}_${h}`;
}

// ---------------------------------------------------------------------------
// HTTP Responses
// ---------------------------------------------------------------------------

const CORS_HEADERS: Record<string, string> = {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'POST, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type',
    'Access-Control-Max-Age': '86400',
};

function jsonResponse(body: object, status: number = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json', ...CORS_HEADERS },
    });
}

// ---------------------------------------------------------------------------
// Bun Server
// ---------------------------------------------------------------------------

const server = Bun.serve({
    port: PORT,
    hostname: '0.0.0.0',
    async fetch(req: Request): Promise<Response> {
        const url = new URL(req.url);

        // CORS preflight
        if (req.method === 'OPTIONS') {
            return new Response(null, { status: 204, headers: CORS_HEADERS });
        }

        // Health check
        if (url.pathname === '/health' && req.method === 'GET') {
            return jsonResponse({ status: 'ok' });
        }

        // Metric ingestion endpoint — mirrors POST /api/metrics
        if (
            (url.pathname === '/api/metrics' ||
                url.pathname === '/api/v1/metrics') &&
            req.method === 'POST'
        ) {
            return handleMetricIngestion(req);
        }

        return jsonResponse({ error: 'Not Found' }, 404);
    },
});

async function handleMetricIngestion(req: Request): Promise<Response> {
    let body: unknown;

    try {
        body = await req.json();
    } catch {
        return jsonResponse({ error: 'Invalid JSON' }, 400);
    }

    const data = parseAndValidate(body);

    if (!data) {
        return jsonResponse({ error: 'Validation failed' }, 422);
    }

    let siteId: number | null = null;

    try {
        siteId = await resolveSiteId(data.license_key);
    } catch (err: any) {
        console.error(`[metrics] Database error: ${err.message}`);

        return jsonResponse({ error: 'Internal Server Error' }, 500);
    }

    if (!siteId) {
        return jsonResponse(
            { error: 'Invalid, expired, or inactive license' },
            401,
        );
    }

    await pushToRedis(siteId, data);

    return jsonResponse({ status: 'ok' });
}

// ---------------------------------------------------------------------------
// Graceful Shutdown
// ---------------------------------------------------------------------------

async function shutdown(): Promise<void> {
    console.log('[metrics] Shutting down gracefully...');
    server.stop(true); // stop accepting new connections, finish in-flight
    redis.disconnect();
    await db.end();
    process.exit(0);
}

process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);

// ---------------------------------------------------------------------------
// Startup
// ---------------------------------------------------------------------------

console.log(`[metrics] Bun metrics server listening on port ${PORT}`);
console.log(`[metrics] Redis prefix: "${REDIS_PREFIX}"`);
console.log(`[metrics] License cache TTL: ${LICENSE_TTL_MS / 1000}s`);
