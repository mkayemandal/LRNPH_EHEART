<?php

/**
 * Small cache. Use APCu if there. Fall back to memory-per-request.
 * Good for: settings, quotas, department lookups. Bad for: live status (PENDING/APPROVED etc).
 */
class CacheService
{
    private static array $local = [];

    public static function remember(string $key, int $ttlSeconds, callable $loader)
    {
        if (function_exists('apcu_fetch')) {
            $hit = false;
            $val = apcu_fetch($key, $hit);
            if ($hit) {
                return $val;
            }
            $val = $loader();
            apcu_store($key, $val, $ttlSeconds);
            return $val;
        }

        // no APCu: memoize for this request only
        if (array_key_exists($key, self::$local)) {
            return self::$local[$key];
        }
        return self::$local[$key] = $loader();
    }

    public static function forget(string $key): void
    {
        unset(self::$local[$key]);
        if (function_exists('apcu_delete')) {
            apcu_delete($key);
        }
    }

    public static function forgetPrefix(string $prefix): void
    {
        foreach (array_keys(self::$local) as $k) {
            if (str_starts_with($k, $prefix)) {
                unset(self::$local[$k]);
            }
        }
        if (function_exists('apcu_delete') && function_exists('apcu_cache_info')) {
            $info = apcu_cache_info();
            foreach ($info['cache_list'] ?? [] as $entry) {
                $k = $entry['info'] ?? $entry['key'] ?? '';
                if ($k !== '' && str_starts_with($k, $prefix)) {
                    apcu_delete($k);
                }
            }
        }
    }
}
