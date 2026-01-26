<?php

namespace xjryanse\phplite\cache;

use xjryanse\phplite\core\RCache;
/**
 * 车载定位缓存；代理门面
 * @see \xjryanse\phplite\core\RCache
 */
class GCache{
    // 页面配置缓存用3
    protected static $instIndex = 5;

    // 调用实际类的方法
    public static function __callStatic($method, $params) {
        // 运行时，即调用子类
        $inst   = RCache::inst(static::$instIndex);
        return call_user_func_array([$inst, $method], $params);
    }
}
