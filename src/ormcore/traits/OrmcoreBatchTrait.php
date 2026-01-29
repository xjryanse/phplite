<?php

namespace xjryanse\phplite\ormcore\traits;

use xjryanse\phplite\logic\Arrays2d;
/**
 * 模型映射查询逻辑
 */
trait OrmcoreBatchTrait {
    /**
     * 新增全部数据（为了性能，不校验，需确保无重复）
     * @return type
     */
    public function batchInsert(array $data) {
        if($this->uuid){
            throw new Exception('不支持的实例uuid,需为空或0'.$this->uuid);
        }
        $this->dataSdkCheck();

        $tableName  = $this->table;
        $res        = $this->dataSdk->tableBatchDataInsert($tableName, $data);
        return $res;
    }
    /**
     * [ok]
     * @param array $data
     * @return type
     * @throws Exception
     */
    public function batchUpdate(array $data) {
        if($this->uuid){
            throw new Exception('不支持的实例uuid,需为空或0'.$this->uuid);
        }
        $this->dataSdkCheck();

        $tableName  = $this->table;
        $res        = $this->dataSdk->tableBatchDataUpdate($tableName, $data);
        return $res;
    }
    /**
     * 批量删除，只需要传id
     * @param array $ids
     * @return type
     * @throws Exception
     */
    public function batchDelete(array $ids) {
        if($this->uuid){
            throw new Exception('不支持的实例uuid,需为空或0'.$this->uuid);
        }
        $this->dataSdkCheck();
        $tableName  = $this->table;
        $res        = $this->dataSdk->tableBatchDataDelete($tableName, $ids);
        return $res;
    }
    /**
     * 自动校验，有数据更新，无数据新增，
     * @param array $data
     * @return type
     */
    public function batchSave(array $data, $uniqueField='id') {
        if($this->uuid){
            throw new Exception('不支持的实例uuid,需为空或0'.$this->uuid);
        }
        $this->dataSdkCheck();

        // 【1】从源系统中查询数据
        $ids        = Arrays2d::uniqueColumn($data, $uniqueField);
        $con        = [];
        // $con[]      = [$uniqueField,'in',$ids];
        $lists      = static::conList($con);
        //【2】进行比对动作
        $diffs      = Arrays2d::calDiffs($data, $lists);
        // 解构
        ['toAdd' => $toAdd, 'toUpdate' => $toUpdate, 'toDelete' => $toDelete] = $diffs;
        // 执行toAdd数据写入动作；
        $toAdd      ? $this->batchInsert($toAdd) : null;
        $toUpdate   ? $this->batchUpdate($toUpdate) : null;
        if($toDelete){
            $toDeleteIds = Arrays2d::uniqueColumn($toDelete, 'id');
            $this->batchDelete($toDeleteIds);
        }
        
        return true;
    }
    

}
