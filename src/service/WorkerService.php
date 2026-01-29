<?php

namespace xjryanse\phplite\service;

use Workerman\Worker;
use xjryanse\phplite\logic\Arrays;
use xjryanse\phplite\error\ErrorWorker;
use Exception;
/**
 * 2026年1月14日
 * 微服务的workerman启动
 */
class WorkerService {
    protected static $tcp;

    public static function start($port, $ip='0.0.0.0'){
        $url = 'tcp://' . $ip . ':' . $port;
        static::$tcp = new Worker($url);
        static::initOnWorkerStart();
        static::initOnMessage();
        Worker::runAll();
    }

    protected static function initOnWorkerStart(){
        // 20230331:使用定时器主动推送消息
        self::$tcp->onWorkerStart = function($worker){

        };
    }
    
    protected static function initOnMessage(){
        // 收到其他服务的调用请求时，处理业务逻辑
        self::$tcp->onMessage = function ($conn, $data) {
            // 1. 注册异常处理：传入Workerman连接对象
            ErrorWorker::register($conn);
            // throw new \Exception('worker调试');
            // 接收请求，转发处理
            return static::onMsgLogic($conn, $data);
        };
    }
    /**
     * 消息逻辑
     */
    public static function onMsgLogic($conn, $data){
        $startTs = microtime(true) * 1000;
        // 一个url路由，一个传递参数
        $reqArr     = json_decode(trim($data), true);            
        $url        = Arrays::value($reqArr, 'url');
        $param      = Arrays::value($reqArr, 'param');

        $uArr   = explode('/',$url);

        if(count($uArr) <> 3){
            $respJson = static::response(1, 'url路径异常'.count($uArr));
            $conn->send($respJson);
        }
        
        try{

            // 拆解模块；控制器；方法
            $uModule        = $uArr[0];
            $uController    = $uArr[1];
            $uAction        = $uArr[2];

            // 过渡方法：
            $logic = '\\app\\'.$uModule.'\\logic\\'. ucfirst($uController);
            if(class_exists($logic) && method_exists($logic, $uAction)){
                // 这个是新的，启用
                $resp = static::call($uArr, $param);
            } else {
                // 这个是原来的，逐步废弃
                $logic = '\\app\\'.$uModule.'\\logic\\'. ucfirst($uController).'Logic';
                $resp = $logic::$uAction($param);
            }

            $endTs = microtime(true) * 1000;
            $res['ts'] = round($endTs) - round($startTs);

            $respJson = static::response(0, '获取数据成功', $resp, $res);
            $conn->send($respJson);
            // 20260114:关闭连接，避免超时
            $conn->close();
            return true;
        } catch(\Exception $e){
            // 2026年1月27日：增加异常捕获
            $mssg = $e->getMessage();
            $respJson = static::response(1, $mssg);
            $conn->send($respJson);
            // 20260114:关闭连接，避免超时
            $conn->close();
            return true;
            
        }
    }
    /**
     * 封装调用逻辑
     * @param type $uArr
     * @param type $post
     * @return type
     * @throws Exception
     */
    public static function call($uArr, $post){
        $uModule        = $uArr[0];
        $uController    = $uArr[1];
        $uAction        = $uArr[2];

        $logicClass = '\\app\\'.$uModule.'\\logic\\'. ucfirst($uController);
        if(!class_exists($logicClass)){
            throw new Exception('类库'.$logicClass.'不存在');
        }

        $logic  = new $logicClass();
        // 加载初始化方法
        if(method_exists($logicClass, 'initialize')){
            $logic->initialize($post);
        }
        if(!method_exists($logicClass, $uAction)){
            throw new Exception('类库'.$logicClass.'方法'.$uAction.'不存在');
        }
        return $logic->$uAction($post);
    }
    
    public static function response($code, $msg, $data = [], $res = []){
        $res['code']    = $code;
        $res['message'] = $msg;
        $res['data']    = $data;

        return json_encode($res, JSON_UNESCAPED_UNICODE);
    }
    
}
