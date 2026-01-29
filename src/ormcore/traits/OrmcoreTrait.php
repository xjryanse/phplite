<?php

namespace xjryanse\phplite\ormcore\traits;


/**
 * 模型映射查询逻辑
 */
trait OrmcoreTrait {
    /**
     * 核心模型映射的数据表
     * @return type
     */
    public function save(array $data) {
        $this->dataSdkCheck();

        $tableName  = $this->table;
        $res        = $this->dataSdk->tableDataSave($tableName, $data);
        return $res;
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
     * 2026年1月29日 数据保存前转换
     */
    public function dataArrPreCov($dataArr){
        $fields = $this->fieldArr();
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
