<?php

namespace xjryanse\phplite\logic;

use xjryanse\phplite\service\AppRequest;
use Exception;

/**
 * logic 层统一分发：HTTP / Workerman 共用，并在 finally 中记录接口调用量
 */
class LogicDispatch {

    /**
     * 调用 app 模块 logic 方法
     * @param string $module
     * @param string $controller 路由控制器名（Info / info 均可）
     * @param string $action
     * @param array  $post
     * @param array  $get
     * @return mixed
     * @throws Exception
     */
    public static function invoke($module, $controller, $action, $post, $get = []) {
        $path = self::path($module, $controller, $action);
        try {
            return self::doInvoke($module, $controller, $action, $post, $get);
        } finally {
            ApiStats::hit($path);
        }
    }

    /**
     * 请求结束：批量写出统计与日志缓冲
     */
    public static function finishRequest(): void {
        ApiStats::flush();
        LogBuffer::flush();
        AppRequest::clear();
    }

    /**
     * 标准接口 path：file/info/get
     */
    public static function path($module, $controller, $action): string {
        $controllerName = ucfirst((string) $controller);
        $controllerPath = Strings::uncamelize($controllerName);
        return trim((string) $module, '/') . '/' . $controllerPath . '/' . trim((string) $action, '/');
    }

    /**
     * logic 类全名
     */
    public static function logicClass($module, $controller): string {
        return '\\app\\' . $module . '\\logic\\' . ucfirst((string) $controller);
    }

    /**
     * @throws Exception
     */
    private static function doInvoke($module, $controller, $action, $post, $get) {
        $logicClass = self::logicClass($module, $controller);
        if (!class_exists($logicClass)) {
            throw new Exception('类库' . $logicClass . '不存在');
        }

        $logic = new $logicClass();
        if (method_exists($logicClass, 'initialize')) {
            $logic->initialize($post, $get);
        }

        $commMethods = Controller::commMethods();
        if (!method_exists($logicClass, $action) && !in_array($action, $commMethods, true)) {
            throw new Exception('类库' . $logicClass . '方法' . $action . '不存在');
        }

        return $logic->$action($post, $get);
    }
}
