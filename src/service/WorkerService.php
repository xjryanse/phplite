<?php

namespace xjryanse\phplite\service;

use Workerman\Worker;
use Workerman\Timer;
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
        // 开发模式代码更新
        static::simpleHotReload();
        
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
            if(class_exists($logic)){
                if(!method_exists($logic, $uAction)){
                    throw new Exception('类'.$logic.'方法'.$uAction.'不存在');
                }
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
    
    /**
     * 极简版热重载（支持监听子目录文件）
     */
    protected static function simpleHotReload() {
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

            // 检测到文件修改，执行重启
            if ($currentMtime > $lastMtime) {
                echo "[" . date('Y-m-d H:i:s') . "] 代码变更，自动重启Workerman\n";

                // 优化重启逻辑：先判断进程是否存在，避免重复重启报错
                $restartCmd = 'php ' . escapeshellarg(ROOT_PATH . 'start.php') . ' restart -d';
                exec($restartCmd, $output, $returnVar);

                if ($returnVar !== 0) {
                    echo "【错误】重启失败：" . implode("\n", $output) . "\n";
                }

                $lastMtime = $currentMtime;
                Worker::stopAll();
            }
        });
    }
}
