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
    /** @var self|null 当前进程内正在处理的请求实例 */
    private static $current = null;

    /** @var array<string,mixed> TCP 入站原始 JSON 解析结果 */
    private $reqArr;

    /** @var string 本微服务入站路由，如 user/User/getInfo */
    private $url;

    /** @var array<string,mixed> 入站业务参数 param */
    private $param;

    /**
     * @var array<string,string> 入站包 ctx（上游调用链）
     * 常见键：trace_id、caller_service、caller_route、caller_from、caller_ip、caller_runtime、caller_peer_ip
     */
    private $ctx;

    /** @var string 本请求链路 ID；入站 ctx 无则自动生成 */
    private $traceId;

    /** @var string TCP 对端 IP（连接 remoteIp） */
    private $peerIp;

    /** @var string 运行时标识，Worker 固定为 worker */
    private $runtime = 'worker';

    /** @var list<array<string,mixed>> 本请求内出站微服务调用 span（SdkTrace 写入） */
    private $serviceSpans = [];

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
     * 本微服务入站路由 module/controller/action。
     */
    public function url(): string
    {
        return $this->url;
    }

    /**
     * 入站业务参数（原始 param，未脱敏）。
     *
     * @return array<string,mixed>
     */
    public function param(): array
    {
        return $this->param;
    }

    /**
     * 入站调用链上下文（上游谁调用了本服务）。
     *
     * @return array<string,string>
     */
    public function ctx(): array
    {
        return $this->ctx;
    }

    /**
     * 链路 TraceId；出站调用与告警应使用同一 ID。
     */
    public function traceId(): string
    {
        return $this->traceId;
    }

    /**
     * TCP 连接对端 IP。
     */
    public function peerIp(): string
    {
        return $this->peerIp;
    }

    /**
     * 运行时，Worker 场景为 worker。
     */
    public function runtime(): string
    {
        return $this->runtime;
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
     * TCP 入站原始解析数组（含 url、param、ctx 等完整字段）。
     *
     * @return array<string,mixed>
     */
    public function raw(): array
    {
        return $this->reqArr;
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

    /**
     * 追加一条本请求内的出站微服务调用 span。
     *
     * @param array<string,mixed> $span SdkTrace::buildWorkerSpan 等生成的条目
     */
    public function addServiceSpan(array $span): void
    {
        $this->serviceSpans[] = $span;
    }

    /**
     * 批量合并出站 span（如子服务响应 $dev.serviceArr）。
     *
     * @param list<array<string,mixed>> $spans
     */
    public function mergeServiceSpans(array $spans): void
    {
        if ($spans === []) {
            return;
        }
        $this->serviceSpans = array_merge($this->serviceSpans, $spans);
    }

    /**
     * 本请求内累计的出站微服务调用记录，供 ErrNotice 告警正文展示。
     *
     * @return list<array<string,mixed>>
     */
    public function serviceSpans(): array
    {
        return $this->serviceSpans;
    }
}
