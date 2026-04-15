<?php

namespace xjryanse\phplite\curl;

use Exception;

/**
 * 静态 HTTP 客户端封装（cURL）。
 *
 * 阅读顺序：先浏览本类全部 public 方法（对外 API），private 仅为实现细节，集中在文件末尾。
 *
 * - 返回 **原始响应体**：{@see self::getRaw}
 * - 返回 **json_decode 结果**（历史命名 *url系列）：{@see self::geturl} {@see self::posturl} 等
 * - **通用 get/post/put**（兼容 swoole websocket）：{@see self::get} {@see self::post} {@see self::put}
 */
class Query {

    // ————————————————————————————————————————————————————————————————
    // 对外 API · GET（含纯文本）
    // ————————————————————————————————————————————————————————————————

    /**
     * GET 请求，返回原始响应体（不做 json_decode；适用于 CSV/纯文本等接口）
     *
     * @param array $header 完整请求头行（含 Accept 等）
     * @throws Exception curl 错误或 HTTP 状态码 >= 400
     */
    public static function getRaw($url, $header = [], $timeout = 60) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        self::setSslRelaxed($ch);
        self::setReturnTransfer($ch);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)$timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        return self::execCloseRawValidated($ch);
    }

    public static function geturl($url, $header = []) {
        $headerArray = array_merge($header, [
            'Content-type:application/json',
            'Accept:application/json',
        ]);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        self::setSslRelaxed($ch);
        self::setReturnTransfer($ch);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        return self::execCloseJsonAssoc($ch);
    }

    /**
     * GET请求，兼容swoole websocket
     */
    public static function get($url, $header = [], $proxy = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        self::applyProxy($ch, $proxy);
        self::setSslRelaxed($ch);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        self::setReturnTransfer($ch);
        return self::execCloseJsonAssoc($ch);
    }

    // ————————————————————————————————————————————————————————————————
    // 对外 API · POST
    // ————————————————————————————————————————————————————————————————

    public static function posturl($url, $data = [], $header = [], $proxy = []) {
        $dataJson      = json_encode($data, JSON_UNESCAPED_UNICODE);
        $headerArray   = array_merge($header, [
            "Content-type:application/json;charset='utf-8'",
            'Accept:application/json',
        ]);
        $contentLength = $data ? strlen($dataJson) : 0;
        $headerArray[] = 'Content-Length:' . $contentLength;

        $ch = curl_init();
        self::applyProxy($ch, $proxy);
        curl_setopt($ch, CURLOPT_URL, $url);
        self::setSslRelaxed($ch);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $dataJson);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        self::setReturnTransfer($ch);
        return self::execCloseJsonAssoc($ch);
    }

    /**
     * POST请求，兼容swoole websocket
     */
    public static function post($url, $data, $header = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        self::setSslRelaxed($ch);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        self::setReturnTransfer($ch);
        return self::execCloseJsonAssoc($ch);
    }

    // ————————————————————————————————————————————————————————————————
    // 对外 API · PUT / DELETE / PATCH（*url 历史命名）
    // ————————————————————————————————————————————————————————————————

    public static function puturl($url, $data, $header = []) {
        $dataJson    = json_encode($data);
        $headerArray = array_merge($header, ['Content-type:application/json']);
        $ch          = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        self::setReturnTransfer($ch);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataJson);
        return self::execCloseJsonAssoc($ch);
    }

    /**
     * put，20251112
     */
    public static function put($url, $data, $header = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        self::setSslRelaxed($ch);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        self::setReturnTransfer($ch);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        return self::execCloseJsonAssoc($ch);
    }

    public static function delurl($url, $data, $header = []) {
        $dataJson    = json_encode($data);
        $headerArray = array_merge($header, ['Content-type:application/json']);
        $ch          = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        self::setReturnTransfer($ch);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $dataJson);
        }
        return self::execCloseJsonAssoc($ch);
    }

    public static function patchurl($url, $data, $header = []) {
        $dataJson    = json_encode($data);
        $headerArray = array_merge($header, ['Content-type:application/json']);
        $ch          = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        self::setReturnTransfer($ch);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataJson);
        return self::execCloseJsonDefault($ch);
    }

    // ————————————————————————————————————————————————————————————————
    // 内部实现（保持各 public 方法历史 curl 选项差异，仅收敛重复片段）
    // ————————————————————————————————————————————————————————————————

    /** @param resource $ch */
    private static function setSslRelaxed($ch) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }

    /** @param resource $ch */
    private static function setReturnTransfer($ch) {
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    }

    /** @param resource $ch */
    private static function applyProxy($ch, $proxy) {
        if ($proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy['host']);
            curl_setopt($ch, CURLOPT_PROXYPORT, $proxy['port']);
        }
    }

    /**
     * @param resource $ch
     * @return mixed json_decode 关联数组
     */
    private static function execCloseJsonAssoc($ch) {
        $output = curl_exec($ch);
        curl_close($ch);
        return json_decode($output, true);
    }

    /**
     * @param resource $ch
     * @return mixed 与历史 patchurl 一致：json_decode($output) 无第二参数
     */
    private static function execCloseJsonDefault($ch) {
        $output = curl_exec($ch);
        curl_close($ch);
        return json_decode($output);
    }

    /**
     * @param resource $ch
     * @throws Exception
     */
    private static function execCloseRawValidated($ch) {
        $output = curl_exec($ch);
        $errno  = curl_errno($ch);
        $err    = curl_error($ch);
        $http   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno) {
            throw new Exception('HTTP请求失败：' . $err);
        }
        if ($http >= 400) {
            throw new Exception('HTTP异常：' . $http);
        }
        return (string)$output;
    }
}
