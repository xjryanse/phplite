<?php

namespace xjryanse\phplite\service;

use xjryanse\phplite\logic\Arrays;
use xjryanse\servicesdk\comm\TcpCtx;

/**
 * Workerman 单次 TCP 请求上下文。
 *
 * 微服务 TCP 入站包结构（与 TcpCtx::envelope 一致）：
 * ```json
 * { "url": "module/controller/action", "param": { ... }, "ctx": { ... } }
 * ```
 *
 * 生命周期（由 WorkerService::onMsgLogic 驱动）：
 * 1. bind / bindFromRaw — 解析入站消息并绑定为当前请求
 * 2. 业务处理期间 — 任意代码通过 current() 读取 url、param、ctx 等
 * 3. clear — 请求结束释放，避免长进程内串数据
 *
 * 并发说明：同步 Worker 下单进程内请求串行处理，bind/clear 配对即可；
 * 勿在 Timer、异步回调中依赖 current()（请求可能已结束）。
 *
 * 关联消费方：ErrNotice、TcpCtx（出站 ctx 透传）、SdkTrace（出站 span）。
 */
class WorkerRequest
{
    use \xjryanse\phplite\service\workerRequest\DataGetTrait;
    use \xjryanse\phplite\service\workerRequest\ServiceSpanTrait;
    
    
    /** @var self|null 当前进程内正在处理的请求实例 */
    protected static $current = null;

    /** @var array<string,mixed> TCP 入站原始 JSON 解析结果 */
    protected $reqArr;

    /** @var string 本微服务入站路由，如 user/User/getInfo */
    protected $url;

    /** @var array<string,mixed> 入站业务参数 param */
    protected $param;

    /**
     * @var array<string,string> 入站包 ctx（上游调用链）
     * 常见键：trace_id、caller_service、caller_route、caller_from、caller_ip、caller_runtime、caller_peer_ip
     */
    protected $ctx;

    /** @var string 本请求链路 ID；入站 ctx 无则自动生成 */
    protected $traceId;

    /** @var string TCP 对端 IP（连接 remoteIp） */
    protected $peerIp;

    /** @var string 运行时标识，Worker 固定为 worker */
    protected $runtime = 'worker';

    /** @var list<array<string,mixed>> 本请求内出站微服务调用 span（SdkTrace 写入） */
    protected $serviceSpans = [];

    /**
     * 从 TCP 消息体（JSON 字符串）解析并绑定当前请求。
     *
     * @param string $data   Workerman onMessage 收到的解码后消息体
     * @param string $peerIp 对端 IP，用于补全 ctx.caller_peer_ip
     */
    public static function bindFromRaw(string $data, string $peerIp = ''): self
    {
        $reqArr = json_decode(trim($data), true);
        $reqArr = is_array($reqArr) ? $reqArr : [];
        
//        $this->url      = Arrays::value($reqArr, 'url');
//        $this->param    = Arrays::value($reqArr, 'param') ? : [];
//        $this->ctx      = Arrays::value($reqArr, 'ctx') ? : [];
//        
        return self::bind($reqArr, $peerIp);
    }
    

    

    /**
     * 从已解析的入站数组绑定当前请求。
     *
     * @param array<string,mixed> $reqArr 至少含 url；可选 param、ctx
     * @param string              $peerIp 对端 IP
     */
    public static function bind(array $reqArr, string $peerIp = ''): self
    {
        $peerIp = trim($peerIp);
        $ctx = TcpCtx::absorbFromRequest($reqArr, $peerIp);

        $traceId = trim((string) ($ctx['trace_id'] ?? ''));
        if ($traceId === '') {
            $traceId = uniqid('t' . substr((string) microtime(true), -6) . '_', true);
        }

        $url = Arrays::value($reqArr, 'url');
        $url = is_string($url) ? $url : '';
        $param = Arrays::value($reqArr, 'param');
        $param = is_array($param) ? $param : [];

        $self = new self($reqArr, $url, $param, $ctx, $traceId, $peerIp);
        self::$current = $self;

        return $self;
    }

    /**
     * @param array<string,mixed>  $reqArr
     * @param array<string,mixed>  $param
     * @param array<string,string> $ctx
     */
    private function __construct(
        array $reqArr,
        string $url,
        array $param,
        array $ctx,
        string $traceId,
        string $peerIp
    ) {
        $this->reqArr = $reqArr;
        $this->url = $url;
        $this->param = $param;
        $this->ctx = $ctx;
        $this->traceId = $traceId;
        $this->peerIp = $peerIp;
    }

    /**
     * 获取当前请求实例；非 Worker 请求处理中或 clear 后为 null。
     */
    public static function current(): ?self
    {
        return self::$current;
    }

    /**
     * 请求结束：释放当前实例（含本请求 serviceSpans）。
     * 应在 WorkerService::finishRequest 中调用。
     */
    public static function clear(): void
    {
        self::$current = null;
    }




    /**
     * 将 url 按 / 拆分为 [module, controller, action]。
     *
     * @return list<string> url 为空时返回空数组
     */
    public function urlSegments(): array
    {
        if ($this->url === '') {
            return [];
        }
        return explode('/', $this->url);
    }



    /**
     * 转为 ErrNotice::notice 可用的 context 数组。
     * 合并入站 ctx 与 runtime、url、param、trace_id。
     *
     * @return array<string,mixed>
     */
    public function toErrNoticeCtx(): array
    {
        return TcpCtx::mergeIntoErrNoticeCtx($this->ctx, [
            'runtime'  => $this->runtime,
            'url'      => $this->url,
            'param'    => $this->param,
            'trace_id' => $this->traceId,
        ]);
    }




}
