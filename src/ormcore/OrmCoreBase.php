<?php
namespace xjryanse\phplite\ormcore;

use Exception;
/**
 * 服务基类
 *  = 数据库映射模型类
 */
class OrmCoreBase {

    use \xjryanse\phplite\traits\InstMultiTrait;
    // 单条写操作
    use \xjryanse\phplite\ormcore\traits\OrmcoreTrait;
    // 批量写操作
    use \xjryanse\phplite\ormcore\traits\OrmcoreBatchTrait;
    // 数据查询
    use \xjryanse\phplite\ormcore\traits\OrmcoreQueryTrait;

    // 依赖注入不同类库
    protected $dataSdk;
    public function setDataSdk($dataSdk){
        $this->dataSdk = $dataSdk;
    }
    
    public function dataSdkCheck(){
        if(!$this->dataSdk){
            throw new Exception('没有指定dataSdk实例，请联系开发排查');
        }
    }
    
    protected $table;
    public function setTable($table){
        $this->table = $table;
    }

    /**
     * Workerman 长进程：清空多例与 uuData 行缓存，避免 catalog 更新后仍读旧数据。
     */
    public static function clearProcessInstances(): void
    {
        static::$instances = [];
    }
}
