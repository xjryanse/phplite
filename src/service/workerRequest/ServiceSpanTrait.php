<?php

namespace xjryanse\phplite\service\workerRequest;

/**
 * 
 */
trait ServiceSpanTrait {

    /**
     * 追加一条本请求内的出站微服务调用 span。
     *
     * @param array<string,mixed> $span SdkTrace::buildWorkerSpan 等生成的条目
     */
    public function addServiceSpan(array $span): void {
        $this->serviceSpans[] = $span;
    }

    /**
     * 批量合并出站 span（如子服务响应 $dev.serviceArr）。
     *
     * @param list<array<string,mixed>> $spans
     */
    public function mergeServiceSpans(array $spans): void {
        if ($spans === []) {
            return;
        }
        $this->serviceSpans = array_merge($this->serviceSpans, $spans);
    }
}
