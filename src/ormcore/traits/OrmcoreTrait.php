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

        $res = $this->dataSdk->tableDataUpdate($tableName, $data);
        return $res;
    }

}
