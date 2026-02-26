<?php

namespace xjryanse\phplite\controller;

use xjryanse\phplite\facade\Request;
use xjryanse\phplite\facade\Route;
use xjryanse\phplite\logic\Controller as controllerLogic;
use Exception;
/**
 * 数据表，常规逻辑
 */
abstract class ControllerBase {
    
    use \xjryanse\phplite\traits\ResponseTrait;
    
    public function __construct() {
        // 控制器初始化
        if(method_exists(static::class, 'initialize')){
            $this->initialize();
        }
    }

    public function __call($method, $params){
        $uModule        = Route::module();
        $uController    = Route::controller();
        $uAction        = Route::action();

        $logicClass = '\\app\\'.$uModule.'\\logic\\'. ucfirst($uController);
        if(!class_exists($logicClass)){
            throw new Exception('类库'.$logicClass.'不存在');
        }
        $post   = Request::post();
        $logic  = new $logicClass();
        // 加载初始化方法
        if(method_exists($logicClass, 'initialize')){
            $logic->initialize($post);
        }
        $commMethods = controllerLogic::commMethods();
        if(!method_exists($logicClass, $uAction) && !in_array($uAction, $commMethods)){
            throw new Exception('类库'.$logicClass.'方法'.$uAction.'不存在');
        }
        // 2026年2月1日：增加get参数，方便http使用
        $get  = Request::get();
        $resp = $logic->$uAction($post, $get);
        return $this->dataReturn('请求',$resp);
    }
}
