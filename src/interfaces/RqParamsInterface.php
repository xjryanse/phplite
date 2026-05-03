<?php
namespace xjryanse\phplite\interfaces;

interface RqParamsInterface {
    public function setRequest($request = null);
    // 获取请求协议 (http/https)
    public function scheme(): string;
    // 获取请求方法 (GET/POST等)
    public function method(): string;
    // 获取请求URI
    public function uri(): string;
    // 域名
    public function host();
    // 获取请求头
    public function header(?string $key = null);
    // 获取GET参数
    public function get(?string $key= null);
    // 获取POST参数
    public function post(?string $key= null);
    /**
     * 上传文件（与 $_FILES 对齐）；无 $name 时返回全部文件数组
     * @param string|null $name 表单字段名
     * @return array|null 单文件为含 tmp_name/type/size/name/error 的数组
     */
    public function file(?string $name = null);
    // 标记当前运行环境：fpm；swoole
    public function env();
    
    public function ip();
}
