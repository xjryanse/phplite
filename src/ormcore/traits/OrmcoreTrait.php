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
    public static function save(array $data) {
        $tableName  = static::getTable();
        $res        = DataSdk::tableDataSave($tableName, $data);
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
        $res = DataSdk::tableDataUpdate($tableName, $data);
        return $res;
    }

}
