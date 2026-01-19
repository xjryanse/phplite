<?php

namespace xjryanse\phplite\ormcore\traits;

use xjryanse\servicesdk\data\DataSdk;
use xjryanse\phplite\logic\Arrays;

/**
 * 模型映射查询逻辑
 */
trait OrmcoreQueryTrait {
    //20220617:考虑get没取到值的情况，可以不用重复查询
    protected $hasUuDataQuery = false;
    protected $uuData = [];
    protected $uuid;
    
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
    /**
     * 获取单条数据
     * @return type
     */
    public function get(){
        if(!$this->uuData){
            $tableName              = static::getTable();
            $this->uuData           = DataSdk::tableDataGet($tableName, $this->uuid);
            $this->hasUuDataQuery   = true;
        }
        return $this->uuData;
    }
    
    public function fv($field){
        $info = $this->get();
        return Arrays::value($info, $field);
    }
    /**
     * 
     * @param type $con
     * @return type
     */
    public static function conList($con = []){
        $tableName  = static::getTable();
        return DataSdk::tableDataConList($tableName, $con);
    }

}
