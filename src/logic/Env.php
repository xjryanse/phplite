<?php
namespace xjryanse\phplite\logic;

use Exception;
use xjryanse\phplite\logic\Arrays;
/**
 * 请求
 */
class Env {
    public static function loadEnv($envPath = ROOT_PATH . '/.env') {
        // 初始化返回结果
        $envData = [];        
        // 检查文件存在性 + 读取文件内容（合并逻辑）
        if (!file_exists($envPath) || !$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) {
            return [];
        }

        // 遍历解析每行（简化循环内逻辑）
         foreach ($lines as $line) {
            $line = trim($line);
            
            // 跳过注释行/空行/非键值对行（兼容PHP7.x：用strpos替代str_contains）
            if (empty($line) || str_starts_with($line, '#') || strpos($line, '=') === false) {
                continue;
            }

            // 分割键值对 + 去除首尾空格
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            
            // 4. 批量设置环境变量
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            $envData[$key] = $value; // 存入返回数组，方便调试
        }
        return $envData;
    }
    /***
     * 
     */
    public static function value($key){
        $arr = static::loadEnv();
        return Arrays::value($arr, $key);
    }
}
