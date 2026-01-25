<?php

namespace xjryanse\phplite\ormcore\traits;

use xjryanse\servicesdk\data\DataSdk;
use xjryanse\phplite\logic\Arrays;
use xjryanse\phplite\logic\Arrays2d;
use Exception;
/**
 * 模型映射查询逻辑（有带数据库类型查表）
 */
trait OrmcoreQueryTrait {
    //20220617:考虑get没取到值的情况，可以不用重复查询
    protected $hasUuDataQuery = false;
    protected $uuData = [];
    protected $uuid;

    /**
     * 获取单条数据
     * @return type
     */
    public function get(){
        $this->dataSdkCheck();
        if(!$this->uuData){
            $tableName              = $this->table;
            $this->uuData           = $this->dataSdk->tableDataGet($tableName, $this->uuid);
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
    public function conList($con = [],$orderBy=''){
        $this->dataSdkCheck();
        $tableName              = $this->table;
        $lists = $this->dataSdk->tableDataConList($tableName, $con);
        if($orderBy){
            $lists = Arrays2d::sort($lists, $orderBy);
        }
        return $lists;
    }
    
    /**
     * 2026年1月22日
     * @param type $con
     * @return type
     */
    public function conFind($con = []){
        $this->dataSdkCheck();
        $tableName              = $this->table;
        return $this->dataSdk->tableDataConFind($tableName, $con);
    }
    /**
     * 2026年1月25日
     * @param type $con
     * @param type $order
     * @param type $perPage
     * @param type $having
     * @param type $field
     * @param type $withSum
     * @return type
     */
    public function paginate($con = [], $order = '', $perPage = 10, $having = '', $field = "*", $withSum = false) {        
        // 20240505:自动添加索引，让系统越跑越快
        $this->dataSdkCheck();
        $tableName  = $this->table;
        
        $sMts       = microtime(true) * 1000;
        $pgList     = $this->dataSdk->tableDataPaginate($tableName); 
        // 耗时分析
        $pgList['mts']  = round(microtime(true) * 1000 - $sMts);
        return $pgList;
    }
    

}
