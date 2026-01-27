<?php

namespace xjryanse\phplite\entrance;

define('ROOT_PATH', dirname(__DIR__).'/');
define('STATIC_PATH', ROOT_PATH . 'public/');

use xjryanse\phplite\phpfpm\facade\RqParams;
use xjryanse\phplite\facade\Request;
use xjryanse\phplite\facade\Route;
use xjryanse\phplite\phpfpm\Error;
use xjryanse\phplite\App;

/**
 * 传统phpFpm入口；
 */
class PhpFpmEntry {
    
    /**
     * 入口
     * @global type $stime
     */
    public static function start(){
        // 初始化脚本开始时间
        global $stime;
        $stime= intval(microtime(true) * 1000);
        // 【1】注册异常处理
        Error::register();
        // 【2】处理跨域
        static::cross();
        // 【3】执行主程序
        static::main();
    }
    /**
     * 处理跨域
     */
    private static function cross(){
        //跨域处理开始：20250302
        $origin = isset($_SERVER['HTTP_ORIGIN'])? $_SERVER['HTTP_ORIGIN'] : '';  
        $allow_origin = array(
            'http://localhost:8090',     // 方便本地调试
            'http://localhost:8091',     // 方便本地调试
        );
        if(in_array($origin, $allow_origin)){
            header('Access-Control-Allow-Origin:'.$origin);
            header('Access-Control-Allow-Headers: Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-Requested-With, Request-Uri, sessionid, source, version');
        }
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            exit;
        }
    }
    
    /**
     * 主进程调用
     */
    private static function main(){
        // 数据库连接初始化
        // 应用参数初始化
        try {
            // 应用参数初始化
            $rqParams = RqParams::inst();
            $rqParams->setRequest();
            // route有调用request,所以request在前
            Request::setRqParams($rqParams);
            // 初始化路由参数
            Route::setRqParams($rqParams);
            // 20250201：主程序运行
            $app = new App();
            // 会话初始化
            $app->init();
            $output = $app->run();
        } catch (\Throwable $e) {
            // 4. 强制捕获所有异常（包括Error和Exception）
            // 手动调用异常处理器
            $output = Error::render($e);
        }

        echo $output;
    }
    
}
