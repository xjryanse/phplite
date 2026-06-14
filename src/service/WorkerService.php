<?php

namespace xjryanse\phplite\service;

use Workerman\Worker;
use xjryanse\phplite\error\ErrorWorker;
use xjryanse\phplite\service\WorkerRequest;
/**
 * 2026年1月14日
 * 微服务的workerman启动
 */
class WorkerService {
    
    use \xjryanse\phplite\service\worker\HotReloadTrait;
    use \xjryanse\phplite\service\worker\MessageTrait;

    protected static $tcp;

    /** 兼容 worker.php 等入口对 Worker 实例的获取 */
    public static function tcp(){
        return static::$tcp;
    }

    /**
     * 主入口：一次调用完成启动（worker.php 仅需 WorkerService::start($port)）
     */
    public static function start($port, $ip='0.0.0.0'){
        // TCP初始化
        static::tcpInit($port, $ip);
        // 启动事件：注册类加载；热重载
        static::initOnWorkerStart();
        // 注册消息接收事件
        static::initOnMessage();
        // 启动
        Worker::runAll();
    }

    /**
     * 兼容 start.php 等分步入口：仅创建 Worker，不注册事件、不 run。
     * 若入口只调 tcpInit + run，需在中间自行完成 initOnWorkerStart、initOnMessage 等。
     */
    public static function tcpInit($port, $ip='0.0.0.0'){
        $url = 'tcp://' . $ip . ':' . $port;
        static::$tcp = new Worker($url);
        static::$tcp->protocol = \Workerman\Protocols\Frame::class; // 与 xjryanse TcpSync 收发一致：4字节长度+消息体
    }

    /** 兼容分步入口（worker.php 等）从外部调用 */
    public static function initOnWorkerStart(){
        self::$tcp->onWorkerStart = function($worker){
            // 20260311:预热加载常用类，减少首次 TCP 请求冷启动耗时
            static::warmUpClasses();
            // 热重载定时器放在 Worker 启动后注册，避免 runAll 前 Timer 导致的环境问题
            static::simpleHotReload();
        };
    }

    /**
     * Worker 启动时预加载框架常用类（仅 phplite / servicesdk），减少首次 TCP 冷启动。
     * 业务类（app\、core\ 等）由各项目在入口自行预热。
     */
    protected static function warmUpClasses(){
        $classes = [
            WorkerRequest::class,
            \xjryanse\phplite\logic\LogicDispatch::class,
            \xjryanse\phplite\logic\ApiStats::class,
            \xjryanse\phplite\logic\LogBuffer::class,
            \xjryanse\servicesdk\comm\TcpCtx::class,
            ErrorWorker::class,
            \xjryanse\phplite\tcp\Sync::class,
            \xjryanse\phplite\logic\Redis::class,
        ];
        foreach ($classes as $cls) {
            if (class_exists($cls)) {
                // 触发自动加载，类加载进内存
            }
        }
    }
    
    /** 兼容分步入口（worker.php 等）从外部调用 */
    public static function initOnMessage(){
        // 收到其他服务的调用请求时，处理业务逻辑
        self::$tcp->onMessage = function ($conn, $data) {
            // 请求ip
            $peerIp     = method_exists($conn, 'getRemoteIp') ? trim((string) $conn->getRemoteIp()) : '';
            // 请求上下文
            $wr         = WorkerRequest::bindFromRaw((string) $data, $peerIp);            
            // 1. 注册异常处理：传入Workerman连接对象
            ErrorWorker::register($conn, $wr);
            // throw new \Exception('worker调试');
            // 接收请求，转发处理
            return static::onMsgLogic($conn, $wr);
        };
    }

}
