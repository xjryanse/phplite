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
    public static function save($data) {
        $tableName  = static::getTable();
        $res        = DataSdk::tableDataSave($tableName, $data);
        return $res;
    }

}
