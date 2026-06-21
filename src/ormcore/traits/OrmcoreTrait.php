<?php

namespace xjryanse\phplite\ormcore\traits;

use xjryanse\phplite\logic\SnowFlake;
use xjryanse\phplite\service\AppRequest;
use xjryanse\servicesdk\entry\EntrySdk;
use Exception;

/**
 * 模型映射查询逻辑
 */
trait OrmcoreTrait {
    /**
     * 2026年2月10日：更新
     * @return type
     */
    public function save(array $data) {
        if(isset($data['id']) && $data['id']){
            // 更新
            return static::inst($data['id'])->update($data);
        } else {
            // 新增
            $data['id'] = SnowFlake::generateParticle();
            $res = static::inst()->insert($data);
            // 20260508:返回id
            return $res ? $data['id'] : '';
        }
    }
    
    /**
     * 2026年1月19日
     * @param array $data
     * @return type
     */
    public function insert(array $data){
        $this->dataSdkCheck();
        if($this->uuid){
            throw new Exception('请使用空id实例');
        }
        $fields     = $this->fields();
        if(in_array('company_id', $fields)){
            $data['company_id'] = EntrySdk::globalSvBindCompanyId();
        }
        if (in_array('creater', $fields) && empty($data['creater'])) {
            $sessionUserId = self::resolveSessionUserId();
            if ($sessionUserId !== '') {
                $data['creater'] = $sessionUserId;
            }
        }

        $tableName  = $this->table;
        $this->dataSdk->tableDataInsert($tableName, $data);
        return true;
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

        $fields = $this->fields();
        if (in_array('updater', $fields)) {
            $sessionUserId = self::resolveSessionUserId();
            if ($sessionUserId !== '') {
                $data['updater'] = $sessionUserId;
            }
        }

        $this->dataSdk->tableDataUpdate($tableName, $data);
        // 更新后清理当前实例缓存，保证后续 get() 重新查库
        $this->uuData = [];
        $this->hasUuDataQuery = false;
        return true;
    }

    /**
     * 2026年1月19日
     * @param array $data
     * @return type
     */
    public function delete(){
        $this->dataSdkCheck();
        
        $tableName  = $this->table;

        $this->dataSdk->tableDataDelete($tableName, $this->uuid);
        return true;
    }
    
    /**
     * 2026年1月29日 数据保存前转换
     */
    public function dataArrPreCov($dataArr){
        $fields = $this->fieldArr();
        if(!$fields){
            throw new Exception($this->table.'字段为空，库'.$this->dbId);
        }

        $tmpArr = [];
        foreach($dataArr as &$d){
            $tmp = [];
            foreach($fields as &$v){
                if(!isset($d[$v['COLUMN_NAME']])){
                    continue;
                }
                $value = $d[$v['COLUMN_NAME']];
                // 如果是boolean,转01；
                if(is_bool($value)){
                    $value = intval($value);
                }
                $tmp[$v['COLUMN_NAME']] = $value;
            }
            $tmpArr[] = $tmp;
        }
        return $tmpArr;
    }

    /**
     * 当前请求操作人（统一 AppRequest 上下文）
     */
    protected static function resolveSessionUserId(): string
    {
        $req = AppRequest::current();
        return $req ? $req->sessionUserId() : '';
    }
}
