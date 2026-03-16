<?php

namespace xjryanse\phplite\tcp;

use Exception;
/**
 * tcp同步请求动作
 * 支持短连接(PHP-FPM)与长连接(Workerman)两种模式
 */
class Sync {
    /**
     * 连接池：host:port => ['socket'=>resource, 'last_used'=>timestamp]
     * 仅 Workerman 等常驻进程下使用
     */
    protected static $connections = [];

    /**
     * 空闲超时（秒），超时后关闭连接
     */
    protected static $idleTimeout = 60;

    /**
     * 是否启用长连接（null=自动检测）
     * @var bool|null
     */
    protected static $useLongConnection = null;

    /**
     * 是否处于长连接环境（Workerman 等常驻进程）
     */
    protected static function isLongConnectionEnv(): bool {
        if (static::$useLongConnection !== null) {
            return static::$useLongConnection;
        }
        // 自动检测：Workerman 运行时 Worker::getAllWorkers() 非空
        if (class_exists('Workerman\Worker')) {
            $workers = \Workerman\Worker::getAllWorkers();
            return !empty($workers);
        }
        return false;
    }

    /**
     * 强制设置长连接模式（用于测试或显式配置）
     */
    public static function setLongConnection(bool $enable): void {
        static::$useLongConnection = $enable;
    }

    /**
     * TCP同步调用
     */
    public static function request($host, $port, $send_data, $timeout = 20) {
        if (static::isLongConnectionEnv()) {
            return static::requestLong($host, $port, $send_data, $timeout);
        }
        return static::requestShort($host, $port, $send_data, $timeout);
    }

    /**
     * 短连接：每次请求建立连接，收发后关闭（PHP-FPM 使用）
     */
    protected static function requestShort($host, $port, $send_data, $timeout = 20) {
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errmsg, $timeout);
        if (!$socket) {
            throw new Exception('连接服务失败:'.$host.'端口:'.$port);
        }
        try {
            stream_set_timeout($socket, $timeout);
            static::sendFrame($socket, $send_data);
            $response = static::recvFrame($socket);
            return json_decode(trim($response), true);
        } finally {
            @fclose($socket);
        }
    }

    /**
     * 长连接：复用连接池（Workerman 等常驻进程使用）
     */
    protected static function requestLong($host, $port, $send_data, $timeout = 20) {
        $key = "{$host}:{$port}";
        $now = time();

        // 获取或创建连接
        $socket = static::getOrCreateConnection($key, $host, $port, $timeout);
        if (!$socket) {
            throw new Exception('连接服务失败:'.$host.'端口:'.$port);
        }

        try {
            stream_set_timeout($socket, $timeout);
            static::sendFrame($socket, $send_data);
            $response = static::recvFrame($socket);
            static::$connections[$key]['last_used'] = $now;
            return json_decode(trim($response), true);
        } catch (\Throwable $e) {
            // 连接异常时从池中移除
            static::removeConnection($key);
            throw $e;
        }
    }

    /**
     * 获取或创建连接
     */
    protected static function getOrCreateConnection(string $key, string $host, string $port, int $timeout) {
        $now = time();
        // 检查现有连接是否有效
        if (isset(static::$connections[$key])) {
            $conn = static::$connections[$key];
            $socket = $conn['socket'];
            // 空闲超时则关闭
            if ($now - $conn['last_used'] > static::$idleTimeout) {
                @fclose($socket);
                unset(static::$connections[$key]);
            } elseif (is_resource($socket) && !feof($socket)) {
                return $socket;
            } else {
                unset(static::$connections[$key]);
            }
        }

        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errmsg, $timeout);
        if (!$socket) {
            return null;
        }
        static::$connections[$key] = ['socket' => $socket, 'last_used' => $now];
        return $socket;
    }

    protected static function removeConnection(string $key): void {
        if (isset(static::$connections[$key])) {
            $socket = static::$connections[$key]['socket'];
            if (is_resource($socket)) {
                @fclose($socket);
            }
            unset(static::$connections[$key]);
        }
    }

    /**
     * Frame 协议发送：4字节长度(大端) + 消息体
     */
    protected static function sendFrame($socket, $send_data): void {
        $body = is_string($send_data) ? $send_data : json_encode($send_data);
        $packet = pack('N', 4 + strlen($body)) . $body;
        $written = 0;
        $len = strlen($packet);
        while ($written < $len) {
            $n = fwrite($socket, substr($packet, $written));
            if ($n === false || $n === 0) {
                throw new Exception('TCP 发送失败');
            }
            $written += $n;
        }
    }

    /**
     * Frame 协议接收：先读4字节长度，再读 body
     */
    protected static function recvFrame($socket): string {
        $lenBuf = '';
        while (strlen($lenBuf) < 4) {
            $chunk = fread($socket, 4 - strlen($lenBuf));
            if ($chunk === false || $chunk === '') {
                $meta = @stream_get_meta_data($socket);
                $timedOut = is_array($meta) && !empty($meta['timed_out']);
                $eof = @feof($socket);
                $peer = @stream_socket_get_name($socket, true) ?: '';
                $extra = [];
                if ($timedOut) {
                    $extra[] = 'timed_out=1';
                }
                if ($eof) {
                    $extra[] = 'eof=1';
                }
                if ($peer) {
                    $extra[] = 'peer=' . $peer;
                }
                $extraStr = $extra ? (' [' . implode(',', $extra) . ']') : '';
                throw new Exception('TCP 接收长度头失败' . $extraStr);
            }
            $lenBuf .= $chunk;
        }
        $unpack = unpack('Nlen', $lenBuf);
        $totalLen = $unpack['len'];
        if ($totalLen < 4 || $totalLen > 1024 * 1024) {
            throw new Exception('TCP 协议异常: 长度非法 ' . $totalLen);
        }
        $bodyLen = $totalLen - 4;
        $body = '';
        while (strlen($body) < $bodyLen) {
            $chunk = fread($socket, min(65535, $bodyLen - strlen($body)));
            if ($chunk === false || $chunk === '') {
                throw new Exception('TCP 接收消息体失败');
            }
            $body .= $chunk;
        }
        return $body;
    }

    /**
     * 关闭指定 host:port 的连接（用于主动释放）
     */
    public static function closeConnection(string $host, string $port): void {
        static::removeConnection("{$host}:{$port}");
    }

    /**
     * 关闭所有长连接
     */
    public static function closeAllConnections(): void {
        foreach (array_keys(static::$connections) as $key) {
            static::removeConnection($key);
        }
    }
}
