<?php

namespace xjryanse\phplite\core;

use xjryanse\phplite\logic\Strings;
use xjryanse\phplite\traits\InstMultiTrait;

/**
 * 本地 JSON 文件缓存（无 Redis 依赖）。
 * API 与 {@see RCache} 对齐：get / set / rm / exists / funcGet / clearAll / cacheKey。
 *
 * 默认目录：{ROOT_PATH}/runtime/file_cache/{uuid}/
 * uuid 由 {@see inst()} 传入，用于区分业务缓存分区（如 catalog、page 等）。
 */
class FileCache
{
    use InstMultiTrait;

    /** 与 RCache 一致：值为 1 时 funcGet 跳过缓存 */
    public const SKIP_KEY = 'sys:cache:skip';

    private const SKIP_MEMO_TTL = 2;

    /** @var string */
    private $baseDir = '';

    /** @var int */
    private $skipMemoAt = 0;

    /** @var bool|null */
    private $skipMemoOn = null;

    protected function instInit(): void
    {
        $this->baseDir = static::defaultBaseDir() . DIRECTORY_SEPARATOR . (string) $this->uuid;
    }

    /**
     * 缓存根目录（不含 uuid 子目录）。
     */
    public static function defaultBaseDir(): string
    {
        if (defined('ROOT_PATH') && ROOT_PATH !== '') {
            return rtrim((string) ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'file_cache';
        }

        return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'phplite_file_cache';
    }

    /**
     * 覆盖当前实例根目录（须在首次读写前调用）。
     */
    public function setBaseDir(string $dir): void
    {
        $this->baseDir = rtrim($dir, '/\\');
    }

    public function getBaseDir(): string
    {
        return $this->baseDir;
    }

    /**
     * @param mixed $value
     */
    public function set(string $key, $value, int $expire = 0): bool
    {
        $path = $this->filePath($key);
        if (!$this->ensureDir(dirname($path))) {
            return false;
        }

        $payload = [
            'key'    => $key,
            'expire' => $expire > 0 ? time() + $expire : 0,
            'time'   => time(),
            'data'   => $value,
        ];

        return $this->writePayload($path, $payload);
    }

    /**
     * @return mixed|null 不存在或已过期返回 null
     */
    public function get(string $key)
    {
        $payload = $this->readPayload($this->filePath($key));
        if ($payload === null) {
            return null;
        }

        $expire = (int) ($payload['expire'] ?? 0);
        if ($expire > 0 && $expire <= time()) {
            $this->rm($key);

            return null;
        }

        return array_key_exists('data', $payload) ? $payload['data'] : null;
    }

    public function rm(string $key): bool
    {
        $path = $this->filePath($key);
        if (!is_file($path)) {
            return false;
        }

        return @unlink($path);
    }

    public function exists(string $key): bool
    {
        return $this->keyState($key) !== -2;
    }

    /**
     * 对标 RCache::keyState
     * -2 不存在；-1 存在且无过期；>=0 剩余秒数
     */
    public function keyState(string $key): int
    {
        $path = $this->filePath($key);
        if (!is_file($path)) {
            return -2;
        }

        $payload = $this->readPayload($path);
        if ($payload === null) {
            return -2;
        }

        $expire = (int) ($payload['expire'] ?? 0);
        if ($expire <= 0) {
            return -1;
        }

        $ttl = $expire - time();
        if ($ttl <= 0) {
            $this->rm($key);

            return -2;
        }

        return $ttl;
    }

    /**
     * 有缓存取缓存，无缓存执行闭包并写入。
     *
     * @param callable(): mixed $func
     */
    public function funcGet(string $key, callable $func, ?int $expire = null)
    {
        if (defined('OPT_DISABLE_CACHE') && OPT_DISABLE_CACHE) {
            return $func();
        }
        if ($this->isSkipOn()) {
            return $func();
        }

        $state = $this->keyState($key);
        if ($state !== -2) {
            return $this->get($key);
        }

        $value = $func();
        $this->set($key, $value, (int) ($expire ?? 0));

        return $value;
    }

    /**
     * @return array<int, string> 逻辑 key 列表
     */
    public function allKeys(): array
    {
        if ($this->baseDir === '' || !is_dir($this->baseDir)) {
            return [];
        }

        $keys = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->baseDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'json') {
                continue;
            }
            if ($fileInfo->getBasename() === '.skip') {
                continue;
            }
            $payload = $this->readPayload($fileInfo->getPathname());
            if ($payload === null || !isset($payload['key'])) {
                continue;
            }
            $keys[] = (string) $payload['key'];
        }

        return array_values(array_unique($keys));
    }

    public function clearAll(): bool
    {
        if ($this->baseDir === '' || !is_dir($this->baseDir)) {
            return true;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->baseDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                @rmdir($fileInfo->getPathname());
            } else {
                @unlink($fileInfo->getPathname());
            }
        }

        return true;
    }

    /**
     * 生成缓存 key（与 RCache 一致）。
     */
    public static function cacheKey(): string
    {
        return md5(json_encode(func_get_args(), JSON_UNESCAPED_UNICODE));
    }

    protected function filePath(string $key): string
    {
        $hash = md5($key);
        $shard = substr($hash, 0, 2);

        return $this->baseDir
            . DIRECTORY_SEPARATOR . $shard
            . DIRECTORY_SEPARATOR . $hash . '.json';
    }

    protected function ensureDir(string $dir): bool
    {
        if (is_dir($dir)) {
            return true;
        }

        return @mkdir($dir, 0775, true) || is_dir($dir);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function writePayload(string $path, array $payload): bool
    {
        $dir = dirname($path);
        if (!$this->ensureDir($dir)) {
            return false;
        }

        $tmp = $path . '.' . getmypid() . '.' . uniqid('', true) . '.tmp';
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        $written = @file_put_contents($tmp, $json, LOCK_EX);
        if ($written === false) {
            @unlink($tmp);

            return false;
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);

            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function readPayload(string $path)
    {
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        if (Strings::isJson($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    protected function isSkipOn(): bool
    {
        $now = time();
        if ($this->skipMemoOn !== null && ($now - $this->skipMemoAt) < self::SKIP_MEMO_TTL) {
            return $this->skipMemoOn;
        }

        $skipFile = $this->baseDir . DIRECTORY_SEPARATOR . '.skip';
        $this->skipMemoOn = is_file($skipFile) && trim((string) @file_get_contents($skipFile)) === '1';
        $this->skipMemoAt = $now;

        return $this->skipMemoOn;
    }
}
