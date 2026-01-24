<?php

namespace xjryanse\phplite\ormcore\traits;

use xjryanse\servicesdk\entry\EntrySdk;
use Exception;
/**
 * 传统php下的逻辑归集（入口适用）
 */
trait DbIdTrait {
    // 指定要查哪个数据库
    protected $dbId;
    /***
     * 
     */
    public function setDbId($dbId){
        $this->dbId = $dbId;
    }
    /**
     * 获取查库id，为空时，用分类转换
     * getDbIdEmptyUseCateCov
     */
    public function getDbIdECV(){
        // 优先使用类内定义方法
        if($this->dbId){
            return $this->dbId;
        }
        // 其次使用类型转换
        return DbSdk::dbId(static::$dbCate);
    }
    

}
