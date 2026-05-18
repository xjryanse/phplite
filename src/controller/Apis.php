<?php

namespace xjryanse\phplite\controller;

use ReflectionClass;
use ReflectionMethod;
use xjryanse\phplite\logic\Strings;

/**
 * 自动提取当前项目的 API 接口列表。
 * 接口来源：app 目录下各模块（排除 index）的 logic 类库（排除 ABase）中各自声明的公开方法。
 * 例如 app\finance\logic\BelongTable 的 dtlGenerate 方法，对应路径 /finance/belong_table/dtlGenerate（类名驼峰转下划线）。
 */
class Apis {

    /** @var string[] 排除的 app 子目录 */
    protected static $excludeModules = ['index'];

    /** @var string[] 排除的 logic 文件名 */
    protected static $excludeLogicFiles = ['ABase.php'];

    /**
     * 提取全部接口
     * @return array<int, array{module:string,controller:string,controller_path:string,action:string,path:string,class:string}>
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
            if (!is_dir($logicDir)) {
                continue;
            }
            $list = array_merge($list, static::scanLogicDir($logicDir, $entry));
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
     * 扫描模块 logic 目录
     */
    protected static function scanLogicDir($logicDir, $module) {
        $list = [];
        foreach (scandir($logicDir) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (substr($file, -4) !== '.php') {
                continue;
            }
            if (in_array($file, static::$excludeLogicFiles, true)) {
                continue;
            }

            $classShort = substr($file, 0, -4);
            $class = 'app\\' . $module . '\\logic\\' . $classShort;
            if (!class_exists($class)) {
                continue;
            }

            $list = array_merge($list, static::extractClassApis($class, $module, $classShort));
        }
        return $list;
    }

    /**
     * 提取单个 logic 类中声明的公开方法（不含父类继承方法）
     */
    protected static function extractClassApis($class, $module, $classShort) {
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

            $list[] = [
                'module'          => $module,
                'controller'      => $classShort,
                'controller_path' => $controllerPath,
                'action'          => $action,
                'path'            => '/' . $module . '/' . $controllerPath . '/' . $action,
                'class'           => $class,
            ];
        }

        return $list;
    }

}
