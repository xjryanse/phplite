<?php

namespace xjryanse\phplite\phpfpm;

use xjryanse\phplite\logic\Arrays;
use xjryanse\phplite\facade\Request;

/**
 * 注册异常处理
 */
class Error {

    use \xjryanse\phplite\traits\ResponseTrait;

    /**
     * 20250202:注册异常处理
     */
    public static function register() {
        // 异常处理
        set_exception_handler([__CLASS__, 'render']);
        // 20250208
        // register_shutdown_function([__CLASS__, 'shutdown']);
    }

    public static function render(\Throwable $e) {

        //有错误的用1
        $res            = [];
        $res['code']    = $e->getCode() ?: 1;
        $res['message'] = $e->getMessage();
        // 20250405：跳转
        // $res['codeJump']= self::getCodeJump($e);
        $res['trace']   = self::getTrace($e);
        
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }
    /**
     * 20250403
     * @param type $e
     * @return type
     */
    protected static function getTrace($e){
        $serverName     = $_SERVER['SERVER_NAME'];
        $prjBasePath    = dirname($_SERVER['DOCUMENT_ROOT']);
        $localPath      = str_replace($prjBasePath, '', $e->getFile());

        $trace          = $e->getTrace();

        $codeHost = $serverName;
        foreach ($trace as &$t) {
            $localPath = str_replace($prjBasePath, '', Arrays::value($t, 'file'));
            $line = Arrays::value($t, 'line');

            $codeJumpUrl        = 'http://localhost:8522/cmd.php?filePath=' . $localPath . '&startLine=' . $line . '&host='. $codeHost;
            // $t['codeJumpUrl']   = $codeJumpUrl;
            // $t['codeJump']      = '<a href="' . $codeJumpUrl .'" target="_blank">' . $localPath . '第' . $line . '行</a>';
        }
        return $trace;
    }
    
    /**
     * 20250403
     * @param type $e
     * @return type
     */
    protected static function getCodeJump($e){
        $codeHost       = $_SERVER['SERVER_NAME'];
        $prjBasePath    = dirname($_SERVER['DOCUMENT_ROOT']);
        $localPath      = str_replace($prjBasePath, '', $e->getFile());

        $line           = $e->getLine();
        $codeJumpUrl        = 'http://localhost:8522/cmd.php?filePath=' . $localPath . '&startLine=' . $line . '&host='. $codeHost;
        // $t['codeJumpUrl']   = $codeJumpUrl;
        $codeJump       = '<a href="' . $codeJumpUrl .'" target="_blank">' . $localPath . '第' . $line . '行</a>';

        return $codeJump;
    }
    
}
