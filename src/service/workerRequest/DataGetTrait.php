<?php

namespace xjryanse\phplite\service\workerRequest;

/**
 * 传统php下的逻辑归集（入口适用）
 */
trait DataGetTrait {

    /**
     * 本微服务入站路由 module/controller/action。
     */
    public function url(): string {
        return $this->url;
    }

    /**
     * 入站业务参数（原始 param，未脱敏）。
     *
     * @return array<string,mixed>
     */
    public function param(): array {
        return $this->param;
    }

    /**
     * 入站调用链上下文（上游谁调用了本服务）。
     *
     * @return array<string,string>
     */
    public function ctx(): array {
        return $this->ctx;
    }

    /**
     * 链路 TraceId；出站调用与告警应使用同一 ID。
     */
    public function traceId(): string {
        return $this->traceId;
    }

    /**
     * TCP 连接对端 IP。
     */
    public function peerIp(): string {
        return $this->peerIp;
    }

    /**
     * 运行时，Worker 场景为 worker。
     */
    public function runtime(): string {
        return $this->runtime;
    }

    /**
     * TCP 入站原始解析数组（含 url、param、ctx 等完整字段）。
     *
     * @return array<string,mixed>
     */
    public function raw(): array {
        return $this->reqArr;
    }

    /**
     * 本请求内累计的出站微服务调用记录，供 ErrNotice 告警正文展示。
     *
     * @return list<array<string,mixed>>
     */
    public function serviceSpans(): array {
        return $this->serviceSpans;
    }
}
