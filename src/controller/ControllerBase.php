<?php

namespace xjryanse\phplite\controller;

use xjryanse\phplite\facade\Request;
use xjryanse\phplite\facade\Route;
use xjryanse\phplite\logic\LogicDispatch;
use xjryanse\phplite\service\AppRequest;
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
        $req = AppRequest::current();
        if ($req !== null) {
            $traceId = $req->traceId();
        } else {
            try {
                $traceId = Request::header('X-Trace-Id');
            } catch (\Throwable $e) {
                $traceId = null;
            }
            if ($traceId === null || $traceId === '') {
                $traceId = uniqid('t' . substr((string)microtime(true), -6) . '_', true);
            }
        }
        $GLOBALS['trace_id'] = $traceId;

        $post = $req ? $req->postParams() : Request::post();
        $get = $req ? $req->getParams() : Request::get();

        $resp = LogicDispatch::invoke(
            Route::module(),
            Route::controller(),
            Route::action(),
            is_array($post) ? $post : [],
            is_array($get) ? $get : []
        );

        LogicDispatch::finishRequest();
        return $this->succReturn('请求成功', $resp);
    }
}
