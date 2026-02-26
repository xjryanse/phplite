<?php

namespace xjryanse\phplite\ormcore\traits;

use xjryanse\phplite\logic\SnowFlake;
use xjryanse\servicesdk\entry\EntrySdk;
use Exception;

/**
 * 模型映射查询逻辑
 */
trait OrmcoreTrait {
    /**
     * 2026年2月10日：更新
     * @return type
     */
    public function save(array $data) {
        if(isset($data['id']) && $data['id']){
            // 更新
            return static::inst($data['id'])->update($data);
        } else {
            // 新增
            $data['id'] = SnowFlake::generateParticle();
            return static::inst()->insert($data);
        }
    }
    
    /**
     * 2026年1月19日
     * @param array $data
     * @return type
     */
    public function insert(array $data){
        $this->dataSdkCheck();
        if($this->uuid){
            throw new Exception('请使用空id实例');
        }
        $fields     = $this->fields();
        if(in_array('company_id', $fields)){
            $data['company_id'] = EntrySdk::globalSvBindCompanyId();
        }

        $tableName  = $this->table;
        $this->dataSdk->tableDataInsert($tableName, $data);
        return true;
    }
    /**
     * 2026年1月19日
     * @param array $data
     * @return type
     */
    public function update(array $data){
        $this->dataSdkCheck();
        
        $tableName  = $this->table;
        $data['id'] = $this->uuid;

        $this->dataSdk->tableDataUpdate($tableName, $data);
        return true;
    }

    /**
     * 2026年1月19日
     * @param array $data
     * @return type
     */
    public function delete(){
        $this->dataSdkCheck();
        
        $tableName  = $this->table;

        $this->dataSdk->tableDataDelete($tableName, $this->uuid);
        return true;
    }
    
    /**
     * 2026年1月29日 数据保存前转换
     */
    public function dataArrPreCov($dataArr){
        $fields = $this->fieldArr();
        if(!$fields){
            throw new Exception($this->table.'字段为空，库'.$this->dbId);
        }

        $tmpArr = [];
        foreach($dataArr as &$d){
            $tmp = [];
            foreach($fields as &$v){
                if(!isset($d[$v['COLUMN_NAME']])){
                    continue;
                }
                $value = $d[$v['COLUMN_NAME']];
                // 如果是boolean,转01；
                if(is_bool($value)){
                    $value = intval($value);
                }
                $tmp[$v['COLUMN_NAME']] = $value;
            }
            $tmpArr[] = $tmp;
        }
        return $tmpArr;
    }
}
