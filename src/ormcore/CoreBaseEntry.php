<?php
namespace xjryanse\phplite\ormcore;

use xjryanse\phplite\ormcore\OrmCoreBase;
use xjryanse\servicesdk\data\DataSdk;

/**
 * 入口文件专用版本
 */
abstract class CoreBaseEntry {
    
    use \xjryanse\phplite\traits\InstMultiTrait;

    public function instInit(){
        static::commInst($this->uuid);
    }
    
    
    // 调用实际类的方法
    public static function __callStatic($method, $params) {
        $inst = static::commInst();
        // 运行时，即调用子类
        return call_user_func_array([$inst, $method], $params);
    }

    public function __call($method, $params) {
        $inst = static::commInst($this->uuid);
        // 运行时，即调用子类
        return call_user_func_array([$inst, $method], $params);
    }

    /**
     * 覆盖下级inst
     * @param type $id
     * @return type
     */
    public static function commInst($id = 0){
        $inst       = OrmCoreBase::inst($id);
        // 此处逻辑不一样，关键直接透传
        // 入口的，必须写死，不然没地方取
        // 注意：这里的sdk配置的是数据库中台，不是Entry本身
        $serverInfo = [
            'workerman_ip'      =>'127.0.0.1',
            'workerman_port'    =>'19914',
            'http_ip'           =>'127.0.0.1',
            'http_port'         =>'9914',
        ];
        $dataSdk    = DataSdk::inst('ENTRY');
        $dataSdk->serverInfoSet($serverInfo);
        $inst->setDataSdk($dataSdk);
        // 设定操作数据表
        $table      = static::getTable();
        $inst->setTable($table);
        return $inst;
    }
    
    
    /**
     * 核心模型映射的数据表
     * @return type
     */
    public static function getTable() {
        // 数据库表前缀
        $prefix         = 'w_';
        $className      = static::class;
        $shortClassName = substr($className, strrpos($className, '\\') + 1);
        $tableNameN     = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortClassName));
        return $prefix.$tableNameN;
    }
}
