<?php

namespace xjryanse\phplite\ormcore\traits;


/**
 * 模型映射查询逻辑
 */
trait OrmcoreBatchTrait {
    /**
     * 新增全部数据（为了性能，不校验，需确保无重复）
     * @return type
     */
    public function batchInsert(array $data) {
        $this->dataSdkCheck();

        $tableName  = $this->table;
        $res        = $this->dataSdk->tableBatchDataInsert($tableName, $data);
        return $res;
    }
    /**
     * 自动校验，有数据更新，无数据新增，
     * @param array $data
     * @return type
     */
    public function batchSave(array $data, $uniqueField='id') {
        $this->dataSdkCheck();

        $tableName  = $this->table;
        $res        = $this->dataSdk->tableBatchDataSave($tableName, $data, $uniqueField);
        return $res;
    }
    

}
