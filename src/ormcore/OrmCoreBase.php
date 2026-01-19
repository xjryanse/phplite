<?php
namespace xjryanse\phplite\ormcore;

use xjryanse\speedy\core\Orm;
use Exception;
/**
 * 服务基类
 *  = 数据库映射模型类
 */
abstract class OrmCoreBase {
    use \xjryanse\phplite\traits\InstMultiTrait;
    use \xjryanse\phplite\ormcore\traits\OrmcoreTrait;
    use \xjryanse\phplite\ormcore\traits\OrmcoreQueryTrait;
    
    
}
