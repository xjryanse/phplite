<?php

namespace xjryanse\phplite\controller;

use ReflectionClass;
use ReflectionMethod;
use xjryanse\phplite\logic\Strings;

/**
 * 自动提取当前项目的 API 接口列表。
 * 接口来源：app 目录下各模块（排除 index）的 logic、controller 类库（logic 排除 ABase）中各自声明的公开方法。
 * 方法 PHPDoc：第一行为 title（方法标题），第二行为 summary（主要功能）；也可用 @title、@summary。
 */
class Apis {

    /** @var string[] 排除的 app 子目录 */
    protected static $excludeModules = ['index'];

    /** @var string[] 排除的 logic 文件名 */
    protected static $excludeLogicFiles = ['ABase.php'];

    /**
     * 提取全部接口
     * @return array<int, array<string, string>>
     */
    public static function all() {
        $appDir = static::appDir();
        if (!is_dir($appDir)) {
            return [];
        }

        $list = [];
        foreach (scandir($appDir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (in_array($entry, static::$excludeModules, true)) {
                continue;
            }
            $modulePath = $appDir . $entry;
            if (!is_dir($modulePath)) {
                continue;
            }
            $logicDir = $modulePath . DIRECTORY_SEPARATOR . 'logic';
            if (is_dir($logicDir)) {
                $list = array_merge($list, static::scanClassDir($logicDir, $entry, 'logic'));
            }
            $ctrlDir = $modulePath . DIRECTORY_SEPARATOR . 'controller';
            if (is_dir($ctrlDir)) {
                $list = array_merge($list, static::scanClassDir($ctrlDir, $entry, 'controller'));
            }
        }

        return $list;
    }

    /**
     * 仅返回路径列表
     * @return string[]
     */
    public static function paths() {
        return array_column(static::all(), 'path');
    }

    /**
     * 按模块分组
     * @return array<string, array<int, array>>
     */
    public static function grouped() {
        $grouped = [];
        foreach (static::all() as $item) {
            $grouped[$item['module']][] = $item;
        }
        return $grouped;
    }

    /**
     * app 目录绝对路径（末尾带目录分隔符）
     */
    protected static function appDir() {
        $root = defined('ROOT_PATH') ? rtrim(ROOT_PATH, '/\\') : dirname(__DIR__, 5);
        return $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR;
    }

    /**
     * 扫描 logic 或 controller 目录
     */
    protected static function scanClassDir($dir, $module, $layer) {
        $list = [];
        foreach (scandir($dir) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (substr($file, -4) !== '.php') {
                continue;
            }
            if ($layer === 'logic' && in_array($file, static::$excludeLogicFiles, true)) {
                continue;
            }

            $classShort = substr($file, 0, -4);
            $class = 'app\\' . $module . '\\' . $layer . '\\' . $classShort;
            if (!class_exists($class)) {
                continue;
            }

            $list = array_merge($list, static::extractClassApis($class, $module, $classShort, $layer));
        }
        return $list;
    }

    /**
     * 提取单个类中声明的公开方法（不含父类继承方法）
     */
    protected static function extractClassApis($class, $module, $classShort, $layer) {
        $ref = new ReflectionClass($class);
        $controllerPath = Strings::uncamelize($classShort);
        $list = [];

        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }
            $action = $method->getName();
            if ($action === '' || $action[0] === '_') {
                continue;
            }

            $doc = static::parseMethodDoc($method);

            $list[] = [
                'module'          => $module,
                'layer'           => $layer,
                'controller'      => $classShort,
                'controller_path' => $controllerPath,
                'action'          => $action,
                'path'            => '/' . $module . '/' . $controllerPath . '/' . $action,
                'class'           => $class,
                'title'           => $doc['title'],
                'summary'         => $doc['summary'],
            ];
        }

        return $list;
    }

    /**
     * 从方法 PHPDoc 解析 title、summary
     * @return array{title:string,summary:string}
     */
    protected static function parseMethodDoc(ReflectionMethod $method) {
        $raw = $method->getDocComment();
        if ($raw === false || trim($raw) === '') {
            return ['title' => '', 'summary' => ''];
        }

        $title = '';
        $summary = '';
        if (preg_match('/@title\s+(.+?)(?:\n|\*\/)/us', $raw, $m)) {
            $title = trim($m[1]);
        }
        if (preg_match('/@summary\s+(.+?)(?:\n\s*\*?\s*@|\*\/)/us', $raw, $m)) {
            $summary = static::normalizeDocText($m[1]);
        } elseif (preg_match('/@summary\s+(.+)/us', $raw, $m)) {
            $summary = static::normalizeDocText($m[1]);
        }

        $lines = static::docTextLines($raw);
        if ($title === '' && $lines !== []) {
            $title = array_shift($lines);
        }
        if ($summary === '' && $lines !== []) {
            $summary = implode(' ', $lines);
        }

        return ['title' => $title, 'summary' => $summary];
    }

    /**
     * @return list<string>
     */
    protected static function docTextLines($raw) {
        $raw = preg_replace('/^\/\*\*|\*\/$/s', '', $raw);
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = preg_replace('/^\s*\*\s?/', '', $line);
            $line = trim($line);
            if ($line === '' || $line[0] === '@') {
                continue;
            }
            $lines[] = $line;
        }
        return $lines;
    }

    protected static function normalizeDocText($text) {
        $text = preg_replace('/\s*\*\s*/', ' ', $text);
        return trim(preg_replace('/\s+/', ' ', $text));
    }

}
