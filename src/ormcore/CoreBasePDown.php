<?php
namespace xjryanse\phplite\ormcore;

use xjryanse\phplite\ormcore\OrmCoreBase;
use xjryanse\servicesdk\data\DataSdk;
use xjryanse\servicesdk\DbSdk;
use Exception;

/**
 * 参数透传版本
 */
abstract class CoreBasePDown {
    
    use \xjryanse\phplite\traits\InstMultiTrait;
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

    protected static $hostBindId;
    /**
     * 设定外部绑定id
     */
    public static function setHostBind($hostBindId){
        static::$hostBindId = $hostBindId;
        return static::commInst();
    }
    
    /**
     * 覆盖下级inst
     * @param type $id
     * @return type
     */
    public static function commInst($id = 0){
        $inst       = OrmCoreBase::inst($id);
        // 设定数据库操作sdk实例
        $hostBindId = static::$hostBindId;
        if(!$hostBindId){
            throw new Exception('未设置$hostBindId');
        }
        
        $dbId       = DbSdk::dbId(static::$dbCate);
        $dataSdk    = DataSdk::inst($hostBindId)->dbBind($dbId);
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
