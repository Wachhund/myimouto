<?php
namespace MyImouto;

use Rails;

/**
 * Cache-based rate limiter using time-bucket sliding windows.
 *
 * Uses Rails::cache() (file store) for counters. Each bucket key
 * expires automatically after the window duration.
 */
class RateLimiter
{
    /**
     * Check if an action is allowed and increment the counter.
     * Returns true if allowed, false if rate-limited.
     */
    public static function attempt(string $key, int $max, int $windowSeconds): bool
    {
        if (self::isLimited($key, $max, $windowSeconds)) {
            return false;
        }

        self::hit($key, $windowSeconds);
        return true;
    }

    /**
     * Check if a key is currently rate-limited (without incrementing).
     */
    public static function isLimited(string $key, int $max, int $windowSeconds): bool
    {
        $cacheKey = self::cacheKey($key, $windowSeconds);
        $hits = (int)Rails::cache()->read($cacheKey);
        return $hits >= $max;
    }

    /**
     * Increment the counter for a key (without checking the limit).
     */
    public static function hit(string $key, int $windowSeconds): void
    {
        $cacheKey = self::cacheKey($key, $windowSeconds);
        $hits = (int)Rails::cache()->read($cacheKey);
        Rails::cache()->write($cacheKey, $hits + 1, ['expires_in' => $windowSeconds . ' seconds']);
    }

    /**
     * Return remaining attempts for a key.
     */
    public static function remaining(string $key, int $max, int $windowSeconds): int
    {
        $cacheKey = self::cacheKey($key, $windowSeconds);
        $hits = (int)Rails::cache()->read($cacheKey);
        return max(0, $max - $hits);
    }

    /**
     * Return seconds until the current window expires.
     */
    public static function retryAfter(int $windowSeconds): int
    {
        $bucket = (int)floor(time() / $windowSeconds);
        $nextWindow = ($bucket + 1) * $windowSeconds;
        return max(1, $nextWindow - time());
    }

    private static function cacheKey(string $key, int $windowSeconds): string
    {
        $bucket = (int)floor(time() / $windowSeconds);
        return 'rate_limit:' . $key . ':' . $bucket;
    }
}
