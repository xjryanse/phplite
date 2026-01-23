<?php

namespace xjryanse\phplite\session;

use xjryanse\phplite\logic\Redis as RedisLogic;

class RedisSession {

    use \xjryanse\phplite\traits\InstMultiTrait;
    // redis库索引：session固定存1库；
    protected $rdbIndex = 1;
    /**
     * 
     */
    public static function current(): RedisSession{
        $sessionId = session_id();
        return static::inst($sessionId);
    }
    
    public function redisInst():\Redis{
        return RedisLogic::inst()->rdInst($this->rdbIndex);
    }
    
    public function set($key, $value){
        $sessionId = $this->uuid;
        $redisInst = $this->redisInst();
        return $redisInst->hSet($sessionId, $key, $value);
    }
    
    public function get($key){
        $sessionId = $this->uuid;
        $redisInst = $this->redisInst();
        return $redisInst->hGet($sessionId, $key);
    }
    /**
     * 全部session信息
     */
    public function all(){
        $sessionId = $this->uuid;
        $redis      = $this->redisInst();
        return $msgData = $redis->hGetAll($sessionId);
    }
    
}
