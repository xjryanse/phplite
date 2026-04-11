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
        // 可选：按项目目录落盘，便于在服务器上直接 tail（需开启 LOG_BUFFER_FILE）
        try {
            self::appendToProjectLogFile($items);
        } catch (\Throwable $e) {
            // 与 Redis 一致：落盘失败不阻塞业务
        }
    }

    /**
     * 写入项目目录下日志文件：{ROOT_PATH}/runtime/log/{项目名}/Y-m-d.log
     * 项目名：环境变量 LOG_PROJECT_SLUG，未设则用 basename(ROOT_PATH)（如 service_zzcr）
     * 开启：环境变量 LOG_BUFFER_FILE=1
     */
    private static function appendToProjectLogFile(array $items): void {
        if (empty($items)) {
            return;
        }
        $flag = getenv('LOG_BUFFER_FILE');
        if ($flag === false || $flag === '' || $flag === '0') {
            return;
        }
        if (!defined('ROOT_PATH')) {
            return;
        }
        $slug = self::projectLogSlug();
        $base = rtrim(ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'log'
            . DIRECTORY_SEPARATOR . $slug;
        if (!is_dir($base) && !@mkdir($base, 0755, true)) {
            return;
        }
        $file = $base . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';
        $lines = [];
        foreach ($items as $msg) {
            $lines[] = json_encode($msg, JSON_UNESCAPED_UNICODE);
        }
        @file_put_contents($file, implode("\n", $lines) . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * 日志子目录名，默认与部署目录名一致（如 /www/wwwroot/service_zzcr → service_zzcr）
     */
    private static function projectLogSlug(): string {
        $v = getenv('LOG_PROJECT_SLUG');
        if ($v !== false && $v !== '') {
            return preg_replace('/[^a-zA-Z0-9._-]/', '_', $v);
        }
        $base = basename(rtrim(ROOT_PATH, '/\\'));
        return $base !== '' ? $base : 'app';
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
