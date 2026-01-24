<?php

namespace xjryanse\phplite\ormcore\traits;

use xjryanse\servicesdk\entry\EntrySdk;
use Exception;
/**
 * 传统php下的逻辑归集（入口适用）
 */
trait PhpFpmTrait {
        
    /**
     * phpFpm
     */
    public static function fpmInstWithHostBind(){
        $hostBindId = EntrySdk::currentHostBindId();
        if(!$hostBindId){
            throw new Exception('当前域名没有配置绑定信息$hostBind,请排查');
        }
        return static::inst()->hostBind($hostBindId);
    }
    /**
     * 适用于已经实例化的类库设置参数
     * @return type
     * @throws Exception
     */
    public function fpmHostBind(){
        $hostBindId = EntrySdk::currentHostBindId();
        if(!$hostBindId){
            throw new Exception('当前域名没有配置绑定信息$hostBind,请排查');
        }
        return $this->hostBind($hostBindId);
    }

}
