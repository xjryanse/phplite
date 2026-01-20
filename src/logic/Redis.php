<?php
namespace xjryanse\phplite\logic;

/**
 * 请求
 */
class Redis {
    
    use \xjryanse\phplite\traits\InstTrait;
    
    protected static $redisRaw;

    private $redis = [];
    
    private $default = [
        'host'      =>'127.0.0.1',
        'port'      =>'6379',
        'db'        =>'0',
        'timeout'   =>'0',        
    ];
    
    public function init($conf) {
        $db = Arrays::value($conf, 'db');
        if ($db < 0 || $db > 15) {
            throw new Exception('数据库编号必须在0 - 15之间');
        }

        $host       = Arrays::value($conf, 'host');
        $port       = Arrays::value($conf, 'port');
        $timeout    = Arrays::value($conf, 'timeout');

        $this->redis[$db] = new \Redis();
        $this->redis[$db]->connect($host, $port, $timeout);
        $this->redis[$db]->select($db);
    }
    /**
     * redis连接实例(缓存永)
     * @return type
     */
    public function rdInst($index = 0) {
        if(!$this->redis[$index]){
            $this->init($this->default);
        }
        return $this->redis[$index];
    }
    
    /**
     * 2026年1月17日
     * @param type $msgKey
     * @param type $data
     * @return type
     */
    public function msgUpdate($msgKey, array $data){
        //写入hash
        $redis                  = $this->rdInst();
        $data['update_time']    = date('Y-m-d H:i:s');
        $res = $redis->hMSet($msgKey, $data);
        $redis->expire($msgKey, 1800);
        return $res;
    }
    
    public function msgKVUpdate($msgKey, string $key, $value){
        //写入hash
        $redis      = $this->rdInst();
        return $redis->hSet($msgKey, $key, $value);
    }
    
    public function msgGet($msgKey){
        //写入hash
        $redis      = $this->rdInst();
        return $msgData = $redis->hGetAll($msgKey);
    }

}
