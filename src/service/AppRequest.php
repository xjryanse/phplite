<?php

namespace xjryanse\phplite\service;

use xjryanse\phplite\interfaces\RqParamsInterface;
use xjryanse\phplite\logic\Arrays;
use xjryanse\phplite\logic\LogicDispatch;
use xjryanse\servicesdk\comm\TcpCtx;

/**
 * 统一入站请求上下文（Worker TCP / PHP-FPM / CLI 手动绑定）。
 *
 * 生命周期：
 * 1. bindFromTcp / bindFromFpm / bindManual — 绑定当前请求
 * 2. 业务期间 — AppRequest::current() 读取 param、traceId、sessionUserId 等
 * 3. clear — 请求结束释放（Worker 必须；FPM 建议统一调用）
 */
class AppRequest
{
    use \xjryanse\phplite\service\workerRequest\DataGetTrait;
    use \xjryanse\phplite\service\workerRequest\ServiceSpanTrait;

    /** @var self|null */
    protected static $current = null;

    /** @var array<string,mixed> */
    protected $reqArr;

    /** @var string */
    protected $url;

    /** @var array<string,mixed> */
    protected $param;

    /** @var array<string,mixed> */
    protected $get;

    /** @var array<string,mixed> */
    protected $post;

    /** @var array<string,string> */
    protected $ctx;

    /** @var string */
    protected $traceId;

    /** @var string */
    protected $peerIp;

    /** @var string worker|phpfpm|cli */
    protected $runtime;

    /** @var RqParamsInterface|null PHP-FPM 原始请求适配器 */
    protected $rqParamInst;

    /** @var list<array<string,mixed>> */
    protected $serviceSpans = [];

    /**
     * Workerman TCP 入站绑定。
     */
    public static function bindFromTcp(string $data, string $peerIp = ''): self
    {
        $reqArr = json_decode(trim($data), true);
        $reqArr = is_array($reqArr) ? $reqArr : [];

        return self::bindEnvelope($reqArr, $peerIp, 'worker');
    }

    /**
     * PHP-FPM 入站绑定（需在 RqParams、Route 初始化之后调用）。
     */
    public static function bindFromFpm(RqParamsInterface $rq): self
    {
        $get = $rq->get();
        $post = $rq->post();
        $get = is_array($get) ? $get : [];
        $post = is_array($post) ? $post : [];
        $param = array_merge($get, $post);

        $url = self::resolveFpmUrl();

        $traceId = trim((string) ($rq->header('x-trace-id') ?: ''));
        if ($traceId === '') {
            $traceId = trim((string) ($GLOBALS['trace_id'] ?? ''));
        }
        if ($traceId === '') {
            $traceId = uniqid('t' . substr((string) microtime(true), -6) . '_', true);
        }
        $GLOBALS['trace_id'] = $traceId;

        $peerIp = '';
        try {
            $peerIp = trim((string) $rq->ip());
        } catch (\Throwable $e) {
            $peerIp = '';
        }

        $reqArr = [
            'url'   => $url,
            'param' => $param,
            'ctx'   => [],
        ];

        $self = new self($reqArr, $url, $param, $get, $post, [], $traceId, $peerIp, 'phpfpm', $rq);
        self::$current = $self;

        return $self;
    }

    /**
     * MQ / CLI 等非 HTTP 场景手动绑定。
     *
     * @param array<string,mixed> $param
     * @param array<string,string> $ctx
     */
    public static function bindManual(string $url, array $param, array $ctx = [], string $runtime = 'cli'): self
    {
        $reqArr = [
            'url'   => $url,
            'param' => $param,
            'ctx'   => $ctx,
        ];

        return self::bindEnvelope($reqArr, '', $runtime);
    }

    /**
     * @param array<string,mixed> $reqArr
     */
    public static function bindEnvelope(array $reqArr, string $peerIp = '', string $runtime = 'worker'): self
    {
        $peerIp = trim($peerIp);
        $ctx = TcpCtx::absorbFromRequest($reqArr, $peerIp);

        $traceId = trim((string) ($ctx['trace_id'] ?? ''));
        if ($traceId === '') {
            $traceId = uniqid('t' . substr((string) microtime(true), -6) . '_', true);
        }
        $GLOBALS['trace_id'] = $traceId;

        $url = Arrays::value($reqArr, 'url');
        $url = is_string($url) ? $url : '';
        $param = Arrays::value($reqArr, 'param');
        $param = is_array($param) ? $param : [];

        $self = new self($reqArr, $url, $param, [], $param, $ctx, $traceId, $peerIp, $runtime, null);
        self::$current = $self;

        return $self;
    }

    /**
     * @param array<string,mixed>  $reqArr
     * @param array<string,mixed>  $get
     * @param array<string,mixed>  $post
     * @param array<string,string> $ctx
     */
    protected function __construct(
        array $reqArr,
        string $url,
        array $param,
        array $get,
        array $post,
        array $ctx,
        string $traceId,
        string $peerIp,
        string $runtime,
        ?RqParamsInterface $rqParamInst
    ) {
        $this->reqArr = $reqArr;
        $this->url = $url;
        $this->param = $param;
        $this->get = $get;
        $this->post = $post;
        $this->ctx = $ctx;
        $this->traceId = $traceId;
        $this->peerIp = $peerIp;
        $this->runtime = $runtime;
        $this->rqParamInst = $rqParamInst;
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    public static function clear(): void
    {
        self::$current = null;
    }

    /**
     * @return array<string,mixed>
     */
    public function getParams(string $key = '')
    {
        return $key === '' ? $this->get : Arrays::value($this->get, $key);
    }

    /**
     * @return array<string,mixed>|mixed
     */
    public function postParams(string $key = '')
    {
        if ($key === '') {
            return $this->post !== [] ? $this->post : $this->param;
        }
        if ($this->post !== []) {
            return Arrays::value($this->post, $key);
        }
        return Arrays::value($this->param, $key);
    }

    public function sessionUserId(): string
    {
        $uid = Arrays::value($this->param, 'sessionUserId');
        return ($uid !== '' && $uid !== null) ? (string) $uid : '';
    }

    public function svBindId()
    {
        return Arrays::value($this->param, 'svBindId') ?: Arrays::value($this->get, 'svBindId');
    }

    /**
     * @return mixed
     */
    public function header(string $key = '')
    {
        if ($this->rqParamInst === null) {
            return $key === '' ? [] : null;
        }
        return $this->rqParamInst->header($key);
    }

    /**
     * @return mixed
     */
    public function file(?string $name = null)
    {
        if ($this->rqParamInst === null) {
            return null;
        }
        return $this->rqParamInst->file($name);
    }

    public function env(): string
    {
        if ($this->rqParamInst !== null) {
            return (string) $this->rqParamInst->env();
        }
        return $this->runtime;
    }

    public function host()
    {
        if ($this->rqParamInst === null) {
            return '';
        }
        return $this->rqParamInst->host();
    }

    public function ip(): string
    {
        if ($this->peerIp !== '') {
            return $this->peerIp;
        }
        if ($this->rqParamInst !== null) {
            try {
                return trim((string) $this->rqParamInst->ip());
            } catch (\Throwable $e) {
                return '';
            }
        }
        return '';
    }

    public function urlSegments(): array
    {
        if ($this->url === '') {
            return [];
        }
        return explode('/', $this->url);
    }

    /**
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

    protected static function resolveFpmUrl(): string
    {
        if (!class_exists(\xjryanse\phplite\facade\Route::class)) {
            return '';
        }
        try {
            return LogicDispatch::path(
                \xjryanse\phplite\facade\Route::module(),
                \xjryanse\phplite\facade\Route::controller(),
                \xjryanse\phplite\facade\Route::action()
            );
        } catch (\Throwable $e) {
            return '';
        }
    }
}
