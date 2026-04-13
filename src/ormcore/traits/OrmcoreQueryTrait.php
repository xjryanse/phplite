<?php

namespace xjryanse\phplite\ormcore\traits;

use xjryanse\phplite\logic\Arrays;
use xjryanse\phplite\logic\Arrays2d;
use xjryanse\phplite\logic\SnowFlake;
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
    public function conList($con = [],$orderBy='', $limit = 0){
        $this->dataSdkCheck();
        $tableName              = $this->table;
        $lists = $this->dataSdk->tableDataConList($tableName, $con, $orderBy, $limit);
        if($orderBy){
            $lists = Arrays2d::sort($lists, $orderBy);
        }
        return $lists;
    }

    /**
     * 指定字段的 conList，用于轻量查询（如 keyObj 仅需 id,base_table,page_key,page_name）
     * @param array $con 条件
     * @param string $orderBy 排序
     * @param string $allowFields 允许返回的字段，逗号分隔
     * @return array
     */
    public function conListWithFields($con = [], $orderBy = '', $allowFields = ''){
        $this->dataSdkCheck();
        $tableName = $this->table;
        $lists = $this->dataSdk->tableDataConList($tableName, $con, $orderBy, $allowFields);
        if($orderBy){
            $lists = Arrays2d::sort($lists, $orderBy);
        }
        return $lists;
    }

    /**
     * 2026年1月29日
     * @param type $mainIds:例如店铺id
     * @param type $field   例如shop_id
     * @return type
     */
    public function dtlCount($mainIds, $field){
        $tableName      = $this->table;
        $res = $this->dataSdk->tableDataDtlCount($tableName, $mainIds, $field);
        return $res;
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
    
    protected $uTableId;
    public function setUTableId($uTableId){
        $this->uTableId = $uTableId;
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
    public function paginate($con = [], $order = '', $param = []) {
        // 20240505:自动添加索引，让系统越跑越快
        $this->dataSdkCheck();
        $tableName  = $this->table;
        
        $sMts       = microtime(true) * 1000;
        
        $param['uTableId'] = $this->uTableId;
        $pgList     = $this->dataSdk->tableDataPaginate($tableName, $order, $con, $param); 
        // 耗时分析
        $pgList['mts']  = round(microtime(true) * 1000 - $sMts);
        return $pgList;
    }

    /**
     * 条件计数：优先走 data/table/paginate 仅取总条数（page=1、listRows=1），避免 conList 拉全量。
     * 若分页返回中无法解析总数，则回退为 conList 后 count（大数据量时较慢）。
     */
    public function conCount($con = []) {
        $this->dataSdkCheck();
        $param = [
            'page'        => 1,
            'currentPage' => 1,
            'listRows'    => 1,
            'pageRecords' => 1,
        ];
        $pgList = $this->paginate($con, '', $param);
        $total = $this->extractPaginateTotal($pgList);
        if ($total !== null) {
            return $total;
        }
        $list = $this->conList($con);
        return is_array($list) ? count($list) : 0;
    }

    /**
     * @param array $pgList paginate 返回值（含可能存在的 mts）
     */
    private function extractPaginateTotal($pgList) {
        if (!is_array($pgList)) {
            return null;
        }
        $row = $pgList;
        unset($row['mts']);
        foreach (['totalRecords', 'total', 'totalRows', 'total_result', 'recordCount'] as $k) {
            if (isset($row[$k])) {
                return (int) $row[$k];
            }
        }
        if (!empty($row['pagination']) && is_array($row['pagination'])) {
            foreach (['totalRecords', 'total'] as $k) {
                if (isset($row['pagination'][$k])) {
                    return (int) $row['pagination'][$k];
                }
            }
        }
        return null;
    }
    
    public function fieldArr(){
        $this->dataSdkCheck();
        $tableName  = $this->table;
        return $this->dataSdk->tableFieldArr($tableName); 
    }
    
    /**
     * 获取表字段信息
     */
    public function fields(){
        $fieldArr = $this->fieldArr(); 
        return array_column($fieldArr, 'COLUMN_NAME');
    }
    /**
     * 2026年2月1日
     * @param type $data
     * @throws Exception
     */
    public function commGetIdEG($data, $ifEmptyId = ''){
        if($this->uuid){
            throw new Exception('请使用空实例');
        }
        $id             = static::commGetId($data);
        if (!$id) {
            $data['id'] = $ifEmptyId ? : SnowFlake::generateParticle();
            static::inst()->insert($data);
            $id         = $data['id'];
        }
        return $id;
    }

    /**
     * 2026年2月1日
     * @param type $data
     * @return string
     */
    public static function commGetId($data) {
        $con        = [];
        foreach ($data as $k => $v) {
            $con[]  = [$k, '=', $v];
        }

        $info = static::inst()->conFind($con);
        if($info){
            return $info['id'];
        }
        return '';
        // TODO:内存中获取
        // $id = self::ramValue('id',$con);
        // return $id;
    }


    
    
    
    


    
}
