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

}
