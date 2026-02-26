<?php

namespace xjryanse\phplite\controller;

use xjryanse\phplite\logic\Arrays;
use xjryanse\phplite\logic\Controller as controllerLogic;

use Exception;
/**
 * 数据表，常规逻辑
 */
abstract class LogicABase {
    /**
     * 初始化
     */
    public function initialize($param){
        global $svBindId;
        $svBindId = Arrays::value($param, 'svBindId');
    }

    // 调用实际类的方法
    public function __call($method, $params) {
        // 这里是处理公共
        $commMethods = controllerLogic::commMethods();
        if(in_array($method, $commMethods)){
            $mtTsr = 'comm'.ucfirst($method);
            return $this->coreClass()::$mtTsr($params[0]);
        } else {
            throw new Exception('方法不存在'.$method);
        }

    }
    
}
