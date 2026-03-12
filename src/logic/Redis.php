<?php
namespace xjryanse\phplite\logic;

/**
 * 请求
 */
class Redis {

    use \xjryanse\phplite\traits\InstTrait;

    private $redis = [];

    /** 日志专用 Redis 连接（可配置为跨网独立实例） */
    private $redisLog = null;

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
    public function rdInst($index = 0) :\Redis{
        if(!isset($this->redis[$index]) || !$this->redis[$index]){
            $conf = $this->default;
            $conf['db'] = $index;
            $this->init($conf);
        }
        return $this->redis[$index];
    }

    /**
     * 日志专用 Redis 连接：若配置 REDIS_LOG_HOST 则用独立连接，否则与 rdInst() 一致
     * 2026-03：减轻跨网时对业务 Redis 的影响
     */
    public function rdInstForLog(): \Redis {
        if ($this->redisLog !== null) {
            return $this->redisLog;
        }
        $host = getenv('REDIS_LOG_HOST');
        if ($host !== false && $host !== '') {
            $port = getenv('REDIS_LOG_PORT');
            $port = ($port !== false && $port !== '') ? (int)$port : 6379;
            $db = getenv('REDIS_LOG_DB');
            $db = ($db !== false && $db !== '') ? (int)$db : 0;
            $timeout = getenv('REDIS_LOG_TIMEOUT');
            $timeout = ($timeout !== false && $timeout !== '') ? (float)$timeout : 0;
            $this->redisLog = new \Redis();
            $this->redisLog->connect($host, $port, $timeout);
            $this->redisLog->select($db);
            return $this->redisLog;
        }
        $this->redisLog = $this->rdInst();
        return $this->redisLog;
    }

    /**
     * 批量写入日志 Hash，用于 LogBuffer 请求结束时一次性发送，减轻跨网次数
     * @param array $prepared [ ['key'=>'...', 'data'=>[...]], ... ]，data 值需为 string
     */
    public function msgBatchUpdateForLog(array $prepared): void {
        if (empty($prepared)) {
            return;
        }
        $redis = $this->rdInstForLog();
        $redis->multi(\Redis::PIPELINE);
        foreach ($prepared as $item) {
            $key = $item['key'];
            $data = $item['data'];
            $redis->hMSet($key, $data);
            $redis->expire($key, 1800);
        }
        $redis->exec();
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
