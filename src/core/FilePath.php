<?php
namespace xjryanse\phplite\core;

/**
 * 应用模块目录
 */
class FilePath{
    //【单例】
    use \xjryanse\phplite\traits\InstTrait;
    /*
     * 应用目录
     */
    public function app() {
        $thisPath = ROOT_PATH;
        return $thisPath.'/app/';
    }
    /*
     * 配置目录
     */
    public function config() {
        $thisPath = ROOT_PATH;
        return $thisPath.'/config/';
    }
    /**
     * 模板文件目录
     * @return type
     */
    public function template() {
        // 当前文件目录
        $thisPath = ROOT_PATH;
        return $thisPath.'/template/';
    }

}
