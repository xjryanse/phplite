<?php

namespace xjryanse\phplite\cache;

use xjryanse\phplite\core\RCache;
/**
 * sql缓存；代理门面
 * @see \xjryanse\phplite\core\RCache
 * @method static mixed funcGet($key, $func, $expire = null)设置缓存
 */
class SqlCache{
    // 系统配置用2；
    protected static $instIndex = 4;

    // 调用实际类的方法
    public static function __callStatic($method, $params) {
        // 运行时，即调用子类
        $inst   = RCache::inst(static::$instIndex);
        return call_user_func_array([$inst, $method], $params);
    }
}
