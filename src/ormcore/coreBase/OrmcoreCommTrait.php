<?php

namespace xjryanse\phplite\ormcore\coreBase;

use xjryanse\phplite\logic\Arrays;
use Exception;
/**
 * 这里封装控制器通用的更新方法
 */
trait OrmcoreCommTrait {
    /**
     * 由控制器端调用的通用更新方法
     * @param type $param
     * @return type
     * @throws Exception
     */
    public static function commUpdate($param){
        $data   = Arrays::value($param, 'table_data') ? : $param;
        $id = Arrays::value($data, 'id');
        if(!$id){
            throw new Exception('id必须');
        }

        // 获取表有字段
        $fields     = static::inst()->fields();
        $dataFilter = Arrays::getByKeys($data, $fields);
        // 2026年2月15日:发现报错：没有指定dataSdk实例？？？
        return static::inst($id)->update($dataFilter);
    }
    
    
}
