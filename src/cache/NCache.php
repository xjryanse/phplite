<?php

namespace xjryanse\phplite\cache;

use xjryanse\phplite\core\RCache;
/**
 * 普通缓存；代理门面
 * @see \xjryanse\phplite\core\RCache
 */
class NCache{
    // 普通缓存用0
    protected static $instIndex = 0;

    // 调用实际类的方法
    public static function __callStatic($method, $params) {
        // 运行时，即调用子类
        $inst   = RCache::inst(static::$instIndex);
        return call_user_func_array([$inst, $method], $params);
    }
}
