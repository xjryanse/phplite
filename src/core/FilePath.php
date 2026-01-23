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
        // 当前文件目录
        $thisPath = __DIR__;
        
        return $thisPath.'/../../../../../app/';
    }
    /*
     * 配置目录
     */
    public function config() {
        // 当前文件目录
        $thisPath = __DIR__;
        
        return $thisPath.'/../../../../../config/';
    }
    /**
     * 模板文件目录
     * @return type
     */
    public function template() {
        // 当前文件目录
        $thisPath = __DIR__;
        
        return $thisPath.'/../../../../../template/';
    }

}
