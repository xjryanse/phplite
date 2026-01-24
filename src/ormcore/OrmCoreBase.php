<?php
namespace xjryanse\phplite\ormcore;

/**
 * 服务基类
 *  = 数据库映射模型类
 */
class OrmCoreBase {

    use \xjryanse\phplite\traits\InstMultiTrait;
    use \xjryanse\phplite\ormcore\traits\OrmcoreTrait;
    use \xjryanse\phplite\ormcore\traits\OrmcoreQueryTrait;

    // 依赖注入不同类库
    protected $dataSdk;
    public function setDataSdk($dataSdk){
        $this->dataSdk = $dataSdk;
    }
    
    protected $table;
    public function setTable($table){
        $this->table = $table;
    }
    
}
