<?php

namespace xjryanse\phplite\service;

use Workerman\Worker;
use Workerman\Timer;
use xjryanse\phplite\logic\Arrays;
use xjryanse\phplite\logic\LogBuffer;
use xjryanse\phplite\error\ErrorWorker;
use Exception;
/**
 * 2026年1月14日
 * 微服务的workerman启动
 */
class WorkerService {
    protected static $tcp;

    /** 兼容 worker.php 等入口对 Worker 实例的获取 */
    public static function tcp(){
        return static::$tcp;
    }

    /**
     * 主入口：一次调用完成启动（worker.php 仅需 WorkerService::start($port)）
     */
    public static function start($port, $ip='0.0.0.0'){
        $url = 'tcp://' . $ip . ':' . $port;
        static::$tcp = new Worker($url);
        static::$tcp->protocol = \Workerman\Protocols\Frame::class; // 与 xjryanse TcpSync 收发一致：4字节长度+消息体
        static::initOnWorkerStart();
        static::initOnMessage();
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

    public static function run(){
        Worker::runAll();
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
     * 20260311:Worker 启动时预加载常用类，减少首次请求冷启动
     */
    protected static function warmUpClasses(){
        $classes = [
            \app\data\logic\Sql::class,
            \app\data\logic\ABase::class,
            \app\data\logic\dbOperate\Sql::class,
            \xjryanse\servicesdk\sql\SqlSdk::class,
            \xjryanse\speedy\core\DbOrm::class,
            \xjryanse\phplite\logic\ModelQueryCon::class,
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
            // 1. 注册异常处理：传入Workerman连接对象
            ErrorWorker::register($conn);
            // throw new \Exception('worker调试');
            // 接收请求，转发处理
            return static::onMsgLogic($conn, $data);
        };
    }
    /**
     * 消息逻辑（Workerman 长进程：每请求需独立 TraceId 并在结束时 flush 日志、清理全局）
     */
    public static function onMsgLogic($conn, $data){
        // 2026-03：Workerman 下无 HTTP 头，从 param 取或生成 TraceId，避免多请求串线
        $reqArr = json_decode(trim($data), true);
        $param  = is_array($reqArr) ? Arrays::value($reqArr, 'param') : [];
        $traceId = is_array($param) ? (Arrays::value($param, 'X-Trace-Id') ?: Arrays::value($param, 'trace_id')) : null;
        if ($traceId === null || $traceId === '') {
            $traceId = uniqid('t' . substr((string)microtime(true), -6) . '_', true);
        }
        $GLOBALS['trace_id'] = $traceId;

        $startTs = microtime(true) * 1000;
        $url     = Arrays::value($reqArr, 'url');
        $uArr    = explode('/', $url);

        if (count($uArr) !== 3) {
            $respJson = static::response(1, 'url路径异常' . count($uArr), [], [], $traceId);
            $conn->send($respJson);
            $conn->close();
            static::finishRequest();
            return true;
        }

        try {
            $uModule     = $uArr[0];
            $uController = $uArr[1];
            $uAction     = $uArr[2];

            $logic = '\\app\\' . $uModule . '\\logic\\' . ucfirst($uController);
            if (class_exists($logic)) {
                if (!method_exists($logic, $uAction)) {
                    throw new Exception('类' . $logic . '方法' . $uAction . '不存在');
                }
                $resp = static::call($uArr, $param);
            } else {
                $logic = '\\app\\' . $uModule . '\\logic\\' . ucfirst($uController) . 'Logic';
                $resp = $logic::$uAction($param);
            }

            $endTs = microtime(true) * 1000;
            $res['ts'] = round($endTs) - round($startTs);
            $respJson = static::response(0, '获取数据成功', $resp, $res, $traceId);
            $conn->send($respJson);
            $conn->close();
            static::finishRequest();
            return true;
        } catch (\Exception $e) {
            $mssg = $e->getMessage();
            $respJson = static::response(1, $mssg, [], [], $traceId);
            $conn->send($respJson);
            $conn->close();
            static::finishRequest();
            return true;
        }
    }

    /**
     * 2026-03：Workerman 单次请求结束：批量写日志、清理全局，避免污染下次请求
     */
    protected static function finishRequest(): void {
        LogBuffer::flush();
        unset($GLOBALS['trace_id']);
        if (isset($GLOBALS['serviceTraceArr'])) {
            unset($GLOBALS['serviceTraceArr']);
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
    
    /**
     * @param int    $code
     * @param string $msg
     * @param array  $data
     * @param array  $res   额外字段（如 ts）
     * @param string|null $traceId 2026-03：Workerman 响应中带回 TraceId，便于调用方串联链路
     */
    public static function response($code, $msg, $data = [], $res = [], $traceId = null){
        $res['code']    = $code;
        $res['message'] = $msg;
        $res['data']    = $data;
        if ($traceId !== null && $traceId !== '') {
            $res['trace_id'] = $traceId;
        }
        return json_encode($res, JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * 极简版热重载（支持监听子目录文件）
     */
    /** 兼容分步入口（worker.php 等）从外部调用 */
    public static function simpleHotReload() {
        $watchDir = rtrim(ROOT_PATH . 'app', DIRECTORY_SEPARATOR);
        echo "【调试】监听目录：{$watchDir}\n";

        // 初始时间设为当前时间
        $lastMtime = time();

        Timer::add(3, function () use ($watchDir, &$lastMtime) {
            // 初始化当前最新修改时间
            $currentMtime = $lastMtime;

            // 使用递归函数遍历所有子目录（替代glob，兼容性更好）
            $scanFiles = function ($dir) use (&$scanFiles, &$currentMtime) {
                // 跳过不存在的目录
                if (!is_dir($dir) || !is_readable($dir)) {
                    return;
                }

                // 打开目录句柄
                $dirHandle = opendir($dir);
                if (!$dirHandle) {
                    return;
                }

                // 遍历目录中的所有项
                while (false !== ($file = readdir($dirHandle))) {
                    // 跳过.和..目录
                    if ($file === '.' || $file === '..') {
                        continue;
                    }

                    $fullPath = $dir . DIRECTORY_SEPARATOR . $file;

                    // 如果是目录，递归遍历子目录
                    if (is_dir($fullPath)) {
                        $scanFiles($fullPath);
                        continue;
                    }

                    // 只处理PHP文件（可根据需要扩展其他后缀，如.php,.html等）
                    if (pathinfo($fullPath, PATHINFO_EXTENSION) !== 'php') {
                        continue;
                    }

                    // 获取文件修改时间，加@屏蔽不存在文件的警告
                    $fileMtime = @filemtime($fullPath);
                    if ($fileMtime && $fileMtime > $currentMtime) {
                        $currentMtime = $fileMtime;
                        echo "【调试】检测到修改文件：{$fullPath}，修改时间：" . date('Y-m-d H:i:s', $fileMtime) . "\n";
                    }
                }
                closedir($dirHandle);
            };

            // 执行递归扫描
            $scanFiles($watchDir);

            // 检测到文件修改，执行重启（无 start.php 时仅打日志，不报错）
            if ($currentMtime > $lastMtime) {
                echo "[" . date('Y-m-d H:i:s') . "] 代码变更，自动重启Workerman\n";
                $lastMtime = $currentMtime;
                $entryFile = ROOT_PATH . 'start.php';
                if (is_file($entryFile)) {
                    $restartCmd = 'php ' . escapeshellarg($entryFile) . ' restart -d';
                    exec($restartCmd, $output, $returnVar);
                    if ($returnVar !== 0) {
                        echo "【错误】重启失败：" . implode("\n", $output) . "\n";
                    }
                } else {
                    echo "【调试】未找到 start.php，跳过自动重启，请手动重启\n";
                }
                Worker::stopAll();
            }
        });
    }
}
