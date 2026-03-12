<?php

namespace xjryanse\phplite\logic;

/**
 * 接口日志缓冲：先入队，请求结束时批量写入 Redis，减轻跨网开销
 * 2026-03
 */
class LogBuffer {

    /** @var array 进程内队列 */
    private static $queue = [];

    /** @var bool 是否已注册 shutdown 确保异常时也 flush */
    private static $shutdownRegistered = false;

    /** 单次 flush 最大条数，避免单批过大 */
    private static $maxBatch = 200;

    /**
     * 将一条日志入队，不立即写 Redis
     * @param array $msg 日志内容，需含 string 或可转为 string 的字段
     */
    public static function push(array $msg): void {
        $msg['update_time'] = date('Y-m-d H:i:s');
        self::$queue[] = $msg;
        self::registerShutdownOnce();
        // 超过阈值时主动 flush，避免单请求量特别大
        if (count(self::$queue) >= self::$maxBatch) {
            self::flush();
        }
    }

    /**
     * 将当前队列批量写入 Redis 并清空队列
     */
    public static function flush(): void {
        $items = self::$queue;
        self::$queue = [];
        if (empty($items)) {
            return;
        }
        try {
            $redis = Redis::inst();
            $prepared = [];
            $env = self::env();
            $service = self::serviceName();
            $date = date('Y-m-d');
            foreach ($items as $i => $msg) {
                $key = sprintf('log:%s:%s:%s:%s_%s', $env, $service, $date, str_replace('.', '', microtime(true)), uniqid('', true));
                $data = [];
                foreach ($msg as $k => $v) {
                    $data[$k] = is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE);
                }
                $prepared[] = ['key' => $key, 'data' => $data];
            }
            $redis->msgBatchUpdateForLog($prepared);
        } catch (\Throwable $e) {
            // 写日志失败不阻塞业务，可在此打 error_log
        }
    }

    private static function env(): string {
        $v = getenv('APP_ENV');
        return $v !== false && $v !== '' ? $v : 'prod';
    }

    private static function serviceName(): string {
        $v = getenv('SERVICE_NAME');
        if ($v !== false && $v !== '') {
            return $v;
        }
        return gethostname() ?: 'unknown';
    }

    private static function registerShutdownOnce(): void {
        if (self::$shutdownRegistered) {
            return;
        }
        self::$shutdownRegistered = true;
        register_shutdown_function(function () {
            \xjryanse\phplite\logic\LogBuffer::flush();
        });
    }
}
