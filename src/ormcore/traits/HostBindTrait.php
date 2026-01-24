<?php

namespace xjryanse\phplite\ormcore\traits;

use Exception;
/**
 * 传统php下的逻辑归集（入口适用）
 */
trait HostBindTrait {
    // 上级服务透传的 域名配置绑定号
    protected $bindId;
    /**
     * 设置绑定id:然后再调用
     * 
     * @return $this
     */
    public function hostBind($bindId){
        $this->bindId = $bindId;
        return $this;
    }
    
    public function hostBindCheck(){
        if(!$this->bindId){
            throw new Exception('没有透传bindId');
        }
    }

}
