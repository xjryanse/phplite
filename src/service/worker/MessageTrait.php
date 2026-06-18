<?php

namespace xjryanse\phplite\service\worker;

use xjryanse\phplite\logic\ApiStats;
use xjryanse\phplite\logic\LogicDispatch;
use xjryanse\phplite\logic\ServiceRuntime;
use xjryanse\phplite\service\WorkerRequest;
use xjryanse\servicesdk\ErrNotice;
use Exception;

/**
 * 消息处理相关逻辑
 */
trait MessageTrait {
    /**
     * 消息逻辑（Workerman 长进程：每请求需独立 TraceId 并在结束时 flush 日志、清理全局）
     */
    public static function onMsgLogic($conn, WorkerRequest $wr){
        $startTs    = microtime(true) * 1000;
        $traceId    = $wr->traceId();
        $bizParam   = $wr->param();
        $uArr       = $wr->urlSegments();

        if (count($uArr) !== 3) {
            $respJson = static::response(1, 'url路径异常' . count($uArr), [], [], $traceId);
            return static::finishRequest($conn, $respJson);
        }

        try {
            [$uModule,$uController,$uAction] = $uArr;
            $logic = static::classStrLogic($uModule, $uController);
            // 类不存在
            if(!class_exists($logic)){
                throw new Exception('类' . $logic . '不存在');
            }
            // 方法不存在
            if (!method_exists($logic, $uAction)) {
                throw new Exception('类' . $logic . '方法' . $uAction . '不存在');
            }
            // 执行调用
            $resp = static::call($uArr, $bizParam);

            $endTs = microtime(true) * 1000;
            $res['ts'] = round($endTs) - round($startTs);
            $respJson = static::response(0, '获取数据成功', $resp, $res, $traceId);
        } catch (\Throwable $e) {
            // 业务层已捕获的异常也主动推送，避免仅靠全局异常处理器遗漏。
            static::pushCaughtException($e);
            $mssg = $e->getMessage();
            $respJson = static::response(1, $mssg, [], [], $traceId);
        }
        
        return static::finishRequest($conn, $respJson);
    }
    /**
     * 
     * @param type $uModule
     * @param type $uController
     * @return type
     * 
     */
    protected static function classStrLogic($uModule, $uController){
        return '\\app\\' . $uModule . '\\logic\\' . ucfirst($uController);
    }
    
    /**
     * 封装调用逻辑
     * @param type $uArr
     * @param type $post
     * @return type
     * @throws Exception
     */
    public static function call($uArr, $post){
        return LogicDispatch::invoke(
            $uArr[0],
            $uArr[1],
            $uArr[2],
            $post,
            []
        );
    }

    /**
     * 业务层捕获异常时的告警推送（不影响主流程返回）。
     */
    protected static function pushCaughtException(\Throwable $e, array $context = []): void {
        try {
            if ($context === []) {
                $wr = WorkerRequest::current();
                $context = $wr !== null ? $wr->toErrNoticeCtx() : [];
            }
            if (!isset($context['runtime'])) {
                $context['runtime'] = 'worker';
            }
            ErrNotice::notice($e, $context);
        } catch (\Throwable $ignore) {
            // 告警推送失败不应影响业务响应
        }
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
        $res['message'] = ((int) $code === 0) ? $msg : ServiceRuntime::prefixMessage($msg);
        $res['data']    = $data;
        if ($traceId !== null && $traceId !== '') {
            $res['trace_id'] = $traceId;
        }
        return json_encode($res, JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * 2026-03：Workerman 单次请求结束：批量写日志、清理全局，避免污染下次请求
     */
    protected static function finishRequest(&$conn, $respJson): bool {
        $conn->send($respJson);
        $conn->close();
        LogicDispatch::finishRequest();
        WorkerRequest::clear();
        return true;
    }
}
