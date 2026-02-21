<?php

namespace xjryanse\phplite\ormcore\coreBase;

/**
 * 这里封装控制器通用的更新方法
 */
trait OrmcoreCacheTrait {

    /**
     * 集中管理缓存规则
     * @param string $method
     * @param type $subFix
     * @return string
     */
    protected static function generateCacheKey(string $method, $subFix = null): string {
        $key = __METHOD__.$method;
        if ($subFix !== null) {
            $key .= $subFix;
        }
        return $key;
    }
    
}
