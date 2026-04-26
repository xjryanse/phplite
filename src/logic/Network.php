<?php
namespace xjryanse\phplite\logic;
use xjryanse\phplite\cache\SCache;

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
        $cacheKey = static::_pingCacheKey($host, $port);
        if ($cacheTtl > 0) {
            if (SCache::exists($cacheKey)) {
                return (bool) SCache::get($cacheKey);
            }
        }
        $result = static::_tcpProbeMs($host, $port, $timeout) !== null;
        if ($cacheTtl > 0) {
            SCache::set($cacheKey, (int) $result, (int) $cacheTtl);
        }
        return $result;
    }

    /**
     * 2026年4月26日：服务端ip地址
     *  ["count"] => int(5)
        ["items"] => array(5) {
          [0] => array(4) {
            ["client_key"] => string(32) "a4f784ef0afaf8e2fc2c4e4edcf9e342"
            ["device_id"] => string(14) "2025-New-XM-V2"
            ["ip"] => array(4) {
              [0] => string(10) "10.9.0.202"
              [1] => string(12) "192.168.80.1"
              [2] => string(13) "192.168.142.1"
              [3] => string(13) "192.168.0.101"
            }
            ["last_seen"] => string(25) "2026-04-26T12:01:28+00:00"
          }
     *  }
     */
    public static function allServerIps(){
        $url = 'http://10.9.0.1:9942/ips';
        $res = \xjryanse\phplite\curl\Query::getUrl($url);
        return $res['items'];
    }
    /**
     * ip转换，得到最快的ip；
     */
    public static function allServerIpConvert($ip,$port=''){
        $key = __METHOD__.$ip.$port;    
        // SCache::rm($key);
        $res = SCache::funcGet($key, function() use ($ip, $port){
            $list = static::allServerIps();
            foreach($list as $v){
                // 无匹配ip；直接下一个
                if(!in_array($ip, $v['ip'])){
                    continue;
                }
                $ips = isset($v['ip']) && is_array($v['ip']) ? $v['ip'] : [];
                if (empty($ips)) {
                    continue;
                }
                return static::getFastestIp($ips, $port ? (int)$port : 80);
            }
            return ['ip' => null, 'time_ms' => null, 'msg' => '未匹配到可用IP'];
        });
        return $res;
    }

    /**
     * 检测单个IP的ping延迟（毫秒）
     * @param string $ip IP地址
     * @param int $timeout 超时时间（秒）
     * @return float|bool 延迟ms，失败返回false
     */
    public static function getIpPingSpeed(string $ip, int $timeout = 1, int $port = 80) {
        $speed = static::_tcpProbeMs($ip, $port, $timeout);
        return $speed === null ? false : $speed;
    }

    /**
     * 从IP列表中获取连接速度最快的IP
     * @param array $ipList IP数组
     * @return array 最快IP + 延迟信息
     */
    public static function getFastestIp(array $ipList, int $port = 80, int $timeout = 1): array {
        $ipSpeeds = [];
        foreach ($ipList as $ip) {
            $speed = static::getIpPingSpeed(trim($ip), $timeout, $port);
            if ($speed !== false) {
                $ipSpeeds[$ip] = $speed;
            }
        }
        if (empty($ipSpeeds)) {
            return ['ip' => null, 'time' => null, 'msg' => '所有IP均不可达'];
        }
        // 按延迟升序排序（最小=最快）
        asort($ipSpeeds);
        $fastestIp = key($ipSpeeds);
        $fastestTime = current($ipSpeeds);
        return [
            'ip' => $fastestIp,
            'time_ms' => $fastestTime,
            'all_result' => $ipSpeeds
        ];
    }

    /**
     * TCP探测耗时（毫秒），失败返回null
     */
    private static function _tcpProbeMs($host, $port = 80, $timeout = 2) {
        $start = microtime(true);
        $socket = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $errmsg,
            $timeout
        );
        if (!$socket) {
            return null;
        }
        fclose($socket);
        return round((microtime(true) - $start) * 1000, 3);
    }

    private static function _pingCacheKey($host, $port) {
        return 'network:ping:' . md5($host . ':' . $port);
    }
}
