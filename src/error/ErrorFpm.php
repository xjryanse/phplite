<?php

namespace xjryanse\phplite\error;

use xjryanse\servicesdk\ErrNotice;

/**
 * 注册异常处理
 */
class ErrorFpm {

    use \xjryanse\phplite\traits\ResponseTrait;

    /**
     * 20250202:注册异常处理
     */
    public static function register() {
        // 异常处理
        set_exception_handler([__CLASS__, 'render']);
    }

    public static function render(\Throwable $e) {
        ErrNotice::notice($e);
        //有错误的用1
        $res            = [];
        $res['code']    = $e->getCode() ?: 1;
        $res['message'] = $e->getMessage();
        
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }
    
}
