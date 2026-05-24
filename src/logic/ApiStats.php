<?php

namespace xjryanse\phplite\logic;

/**
 * 接口调用量统计：请求内缓冲，结束时批量 HINCRBY 写入 Redis
 * 开启：环境变量 API_STATS_ENABLED=1（未设置时默认开启；设为 0 关闭）
 */
class ApiStats {

    /** @var array<string,int> 当前请求进程内计数 path => count */
    private static $buffer = [];

    /** @var bool */
    private static $shutdownRegistered = false;

    /** @var string 当前请求命中的接口 path */
    private static $currentPath = '';

    /** @var int 当前接口累计请求次数（含本次，供 $dev.queryIndex） */
    private static $queryIndex = 0;

    /**
     * 记录一次 logic 接口命中
     */
    public static function hit($path): void {
        if (!self::enabled()) {
            return;
        }
        $path = trim((string) $path, '/');
        if ($path === '') {
            return;
        }
        self::$currentPath = $path;
        if (!isset(self::$buffer[$path])) {
            self::$buffer[$path] = 0;
        }
        self::$buffer[$path]++;
        self::$queryIndex = self::countForPath($path);
        self::registerShutdownOnce();
    }

    /**
     * 返回当前接口今日累计请求次数（含本次），写入 $dev.queryIndex
     */
    public static function queryIndex(): int {
        if (!self::enabled()) {
            return 0;
        }
        if (self::$queryIndex > 0 || self::$currentPath !== '') {
            return self::$queryIndex;
        }
        return 0;
    }

    /**
     * Redis 已持久化次数 + 当前请求缓冲
     */
    private static function countForPath($path): int {
        $count = 0;
        try {
            $redis = Redis::inst()->rdInstForLog();
            $val = $redis->hGet(self::redisKey(), $path);
            if ($val !== false && $val !== null && $val !== '') {
                $count = (int) $val;
            }
        } catch (\Throwable $e) {
            // 读 Redis 失败时仍用进程内缓冲兜底
        }
        if (isset(self::$buffer[$path])) {
            $count += (int) self::$buffer[$path];
        }
        return $count;
    }

    /**
     * 将缓冲批量写入 Redis
     */
    public static function flush(): void {
        $items = self::$buffer;
        self::$buffer = [];
        if (empty($items)) {
            return;
        }
        try {
            $redis = Redis::inst();
            $prepared = [];
            $key = self::redisKey();
            foreach ($items as $path => $inc) {
                $prepared[] = [
                    'key'   => $key,
                    'field' => $path,
                    'inc'   => (int) $inc,
                ];
            }
            $redis->statsBatchIncr($prepared);
        } catch (\Throwable $e) {
            // 统计失败不阻塞业务
        }
    }

    /**
     * 读取某日各接口调用量（供管理端/调试）
     * @return array<string,int>
     */
    public static function readDay($date = null): array {
        $date = $date ?: date('Y-m-d');
        try {
            $redis = Redis::inst()->rdInstForLog();
            $raw = $redis->hGetAll(self::redisKey($date));
            if (!is_array($raw)) {
                return [];
            }
            $res = [];
            foreach ($raw as $path => $count) {
                $res[$path] = (int) $count;
            }
            arsort($res);
            return $res;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function redisKey($date = null): string {
        $date = $date ?: date('Y-m-d');
        $env = getenv('APP_ENV');
        $env = ($env !== false && $env !== '') ? $env : 'prod';
        $service = getenv('SERVICE_NAME');
        if ($service === false || $service === '') {
            if (defined('ROOT_PATH')) {
                $service = basename(rtrim(ROOT_PATH, '/\\')) ?: 'app';
            } else {
                $service = gethostname() ?: 'unknown';
            }
        }
        return sprintf('api:stats:%s:%s:%s', $env, $service, $date);
    }

    private static function enabled(): bool {
        $flag = getenv('API_STATS_ENABLED');
        if ($flag === false || $flag === '') {
            return true;
        }
        return !in_array((string) $flag, ['0', 'false', 'off', 'no'], true);
    }

    private static function registerShutdownOnce(): void {
        if (self::$shutdownRegistered) {
            return;
        }
        self::$shutdownRegistered = true;
        register_shutdown_function(function () {
            self::flush();
        });
    }
}
