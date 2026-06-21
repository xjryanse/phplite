<?php

namespace xjryanse\phplite\error;

use xjryanse\phplite\logic\ServiceRuntime;
use xjryanse\servicesdk\ErrNotice;
use xjryanse\phplite\service\AppRequest;
use Workerman\Connection\ConnectionInterface;
/**
 * 注册异常处理
 */
class ErrorWorker {

    use \xjryanse\phplite\traits\ResponseTrait;

    protected static $connection;
    
    protected static $req;
    /**
     * 20250202:注册异常处理
     */
    public static function register(ConnectionInterface $connection, AppRequest $req) {
        self::$connection = $connection;
        static::$req = $req;
        // 异常处理
        set_exception_handler([__CLASS__, 'render']);
    }

    public static function render(\Throwable $e) {
        $req        = static::$req;
        $context    = $req !== null ? $req->toErrNoticeCtx() : ['runtime' => 'worker'];
        ErrNotice::notice($e, $context);
        //有错误的用1
        $res            = [];
        $res['code']    = $e->getCode() ?: 1;
        $res['message'] = ServiceRuntime::prefixMessage($e->getMessage());
        $res['trace']   = $e->getTrace();
        
        // workerman和php环境处理方法不同
        static::$connection->send(json_encode($res, JSON_UNESCAPED_UNICODE));
    }
    
}
