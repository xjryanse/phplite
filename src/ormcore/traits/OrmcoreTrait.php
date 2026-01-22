<?php

namespace xjryanse\phplite\ormcore\traits;

use xjryanse\servicesdk\data\DataSdk;

/**
 * 模型映射查询逻辑
 */
trait OrmcoreTrait {
    /**
     * 核心模型映射的数据表
     * @return type
     */
    public function save(array $data) {
        $tableName  = static::getTable();
        $dbId       = $this->getDbIdECV();
        $res        = DataSdk::inst($dbId)->tableDataSave($tableName, $data);
        return $res;
    }
    /**
     * 2026年1月19日
     * @param array $data
     * @return type
     */
    public function update(array $data){
        $tableName  = static::getTable();
        $data['id']          = $this->uuid;

        $dbId       = $this->getDbIdECV();        
        $res = DataSdk::inst($dbId)->tableDataUpdate($tableName, $data);
        return $res;
    }

}
