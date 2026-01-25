<?php
/**
 * 20250201:自己的框架用
 */
if (!function_exists('dump')) {

    function dump($var) {
        ob_start();
        if(is_string($var)){
             // 尝试解析 JSON，验证是否为合法 JSON 字符串
            json_decode($var);
            $isJson = (json_last_error() === JSON_ERROR_NONE);
            // 非 JSON 字符串才进行 HTML 转义
            if (!$isJson) {
                $var = htmlspecialchars($var);
            }
        }
        var_dump($var);
        $p1 = ob_get_clean();
        $p2 = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $p1);
        $p3 = '<pre>' . $p2 . '</pre>';
        echo($p3);
    }

}
/**
 * 20250201:自己的框架用
 */
if (!function_exists('json')) {
    function json($var) {
        return json_encode($var, JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('session')) {
    function session($name, $value = null) {
        $argCount = func_num_args();
        if($argCount == 1){
            // 只传一个参数，表示读取session值
            return \xjryanse\phplite\session\RedisSession::current()->get($name);
        }
        // 传多个参数表示写入
        return \xjryanse\phplite\session\RedisSession::current()->set($name, $value);
    }
}

if (!function_exists('config')) {
    function config($key) {
        // 分割键名，例如 'database.prefix' 分割为 ['database', 'prefix']
        $parts = explode('.', $key);
        // 获取配置文件名，例如 'database'
        $file = array_shift($parts);
        // 构建配置文件的路径
        $configFile = ROOT_PATH . '/config/' . $file . '.php';

        // 检查配置文件是否存在
        if (file_exists($configFile)) {
            // 引入配置文件并获取配置数组
            $config = require $configFile;
            // 遍历剩余的键名，逐级访问配置数组
            foreach ($parts as $part) {
                if (isset($config[$part])) {
                    $config = $config[$part];
                } else {
                    // 如果某个键名不存在，返回 null
                    return null;
                }
            }
            return $config;
        }
        // 如果配置文件不存在，返回 null
        return null;
    }

}

