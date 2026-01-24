<?php
namespace xjryanse\phplite\ormcore;

use xjryanse\servicesdk\DbSdk;
use Exception;
/**
 * 服务基类
 *  = 数据库映射模型类
 */
abstract class OrmCoreBase {
    use \xjryanse\phplite\traits\InstMultiTrait;
    use \xjryanse\phplite\ormcore\traits\PhpFpmTrait;
    use \xjryanse\phplite\ormcore\traits\OrmcoreTrait;
    use \xjryanse\phplite\ormcore\traits\OrmcoreQueryTrait;
    
    // 上级服务透传的 域名配置绑定号
    protected $bindId;
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
    
    /**
     * 设置绑定id:然后再调用
     * 
     * @return $this
     */
    public function hostBind($bindId){
        $this->bindId = $bindId;
        return $this;
    }
    
    public function hostBindCheck(){
        if(!$this->bindId){
            throw new Exception('没有透传bindId');
        }
    }

}
