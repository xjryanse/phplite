<?php
namespace xjryanse\phplite\logic;

/*
 * 20251007:网络逻辑
 */
class Network {
    /**
     * 
     * @param type $ip
     * @return bool
     */
    public static function isLocalhostIp($ip) {
        // 特殊情况处理：localhost和127.0.0.1系列
        if ($ip === 'localhost' || strpos($ip, '127.') === 0) {
            return true;
        }
        // 获取服务器所有网络接口的IP地址
        $serverIps  = static::serverIps();
        // 检查目标IP是否在服务器IP列表中
        return in_array($ip, $serverIps);
    }
    
    /**
     * 获取服务器所有IP地址
     * @return array
     */
    public static function serverIps() {
        $ips = [];

        // 根据操作系统执行不同命令
        if (stristr(PHP_OS, 'WIN')) {
            // Windows系统
            exec('ipconfig | findstr /i "IPv4"', $output);
            foreach ($output as $line) {
                preg_match('/\d+\.\d+\.\d+\.\d+/', $line, $matches);
                if (!empty($matches[0])) {
                    $ips[] = $matches[0];
                }
            }
        } else {
            // Linux/Unix/Mac系统
            exec('hostname -I', $output);
            if (!empty($output[0])) {
                $ips = explode(' ', $output[0]);
            }

            // 备选方案（如果hostname命令不可用）
            if (empty($ips)) {
                exec('ifconfig | grep "inet " | grep -v 127.0.0.1', $output);
                foreach ($output as $line) {
                    preg_match('/inet (\d+\.\d+\.\d+\.\d+)/', $line, $matches);
                    if (!empty($matches[1])) {
                        $ips[] = $matches[1];
                    }
                }
            }
        }

        // 添加服务器环境变量中的IP
        if (!empty($_SERVER['SERVER_ADDR'])) {
            $ips[] = $_SERVER['SERVER_ADDR'];
        }

        // 去重并返回
        return array_unique($ips);
    }

    /**
     * 判断指定 IP 或域名是否可达（TCP 连接检测，不依赖 exec）
     * 结果会缓存，在缓存有效期内不重复检测
     * @param string $host IP 地址或域名
     * @param float $timeout 超时秒数，默认 1 秒
     * @param int $port 检测端口，默认 80（HTTP），可改为 443、22 等
     * @param int $cacheTtl 缓存秒数，默认 60 秒，0 表示不缓存
     * @return bool true=可达，false=不可达
     */
    public static function isPingable($host, $timeout = 2, $port = 80, $cacheTtl = 60) {
        $host = trim($host);
        if (empty($host)) {
            return false;
        }
        $cacheKey = "{$host}:{$port}";
        if ($cacheTtl > 0) {
            $cached = static::_getPingCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        $socket = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $errmsg,
            $timeout
        );
        $result = false;
        if ($socket) {
            fclose($socket);
            $result = true;
        }
        if ($cacheTtl > 0) {
            static::_setPingCache($cacheKey, $result, $cacheTtl);
        }
        return $result;
    }

    /** @var array 内存缓存（同请求内）[key => ['result'=>bool, 'expire'=>timestamp]] */
    private static $_pingCache = [];

    private static function _getPingCache($key) {
        if (isset(static::$_pingCache[$key])) {
            $item = static::$_pingCache[$key];
            if (time() <= $item['expire']) {
                return $item['result'];
            }
            unset(static::$_pingCache[$key]);
        }
        $fileCached = static::_getPingFileCache($key);
        if ($fileCached !== null) {
            static::$_pingCache[$key] = ['result' => $fileCached, 'expire' => time() + 10];
            return $fileCached;
        }
        return null;
    }

    private static function _setPingCache($key, $result, $ttl) {
        static::$_pingCache[$key] = ['result' => $result, 'expire' => time() + $ttl];
        static::_setPingFileCache($key, $result, $ttl);
    }

    private static function _getPingFileCacheDir() {
        $dir = sys_get_temp_dir() . '/network_ping_cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private static function _getPingFileCache($key) {
        $file = static::_getPingFileCacheDir() . '/' . md5($key) . '.json';
        if (!is_file($file)) {
            return null;
        }
        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }
        $data = @json_decode($content, true);
        if (!$data || !isset($data['expire']) || time() > $data['expire']) {
            @unlink($file);
            return null;
        }
        return (bool) $data['result'];
    }

    private static function _setPingFileCache($key, $result, $ttl) {
        $file = static::_getPingFileCacheDir() . '/' . md5($key) . '.json';
        $data = ['result' => $result, 'expire' => time() + $ttl];
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }


}
