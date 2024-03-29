<?php

namespace Zls\Cache;

use Z;

/**
 * Zls_Cache_Redis.
 */
class Redis implements \Zls_Cache
{
    private $config;
    private $servers;

    public function __construct($config)
    {
        foreach ($config as $key => $node) {
            if (empty($node['slaves']) && !empty($node['master'])) {
                $config[$key]['slaves'][] = $node['master'];
            }
        }
        $this->config = $config;
    }

    public function reset()
    {
        $this->servers = [];

        return $this;
    }

    public function clean()
    {
        $status = true;
        foreach ($this->config as $nodeIndex => $config) {
            $redis = $this->connect($config['master']);
            $status = $status && $redis->flushDB();
        }

        return $status;
    }

    private function &connect($config)
    {
        $redis = new \Redis();
        if ('sock' == $config['type']) {
            $redis->connect($config['sock']);
        } else {
            if($config['timeout']){
                $redis->connect($config['host'], $config['port'], $config['timeout'], $config['retry']);
            }else{
                $redis->pconnect($config['host'], $config['port'], 0, null,$config['retry']);
            }
        }
        if (!is_null($config['password'])) {
            $redis->auth($config['password']);
        }
        if (!is_null($config['prefix'])) {
            if ($config['prefix'][strlen($config['prefix']) - 1] != ':') {
                $config['prefix'] .= ':';
            }
            $redis->setOption(\Redis::OPT_PREFIX, $config['prefix']);
        }
        $redis->select($config['db']);

        return $redis;
    }

    public function delete($key)
    {
        $redis = $this->selectNode($key, false);

        return $redis->delete($key);
    }

    private function &selectNode($key, $isRead)
    {
        $nodeIndex = sprintf('%u', crc32($key)) % count($this->config);
        if ($isRead) {
            $slaveIndex = array_rand($this->config[$nodeIndex]['slaves']);
            $serverKey = $nodeIndex . '-slaves-' . $slaveIndex;
            $config = $this->config[$nodeIndex]['slaves'][$slaveIndex];
        } else {
            $serverKey = $nodeIndex . '-master';
            $config = $this->config[$nodeIndex]['master'];
        }
        if (empty($this->servers[$serverKey])) {
            $this->servers[$serverKey] = $this->connect($config);
        }

        return $this->servers[$serverKey];
    }

    public function get($key)
    {
        $redis = $this->selectNode($key, true);
        if ($rawData = $redis->get($key)) {
            return @unserialize($rawData);
        } else {
            return null;
        }
    }

    public function set($key, $value, $cacheTime = 0)
    {
        $redis = $this->selectNode($key, false);
        $value = serialize($value);
        if ($cacheTime) {
            return $redis->setex($key, $cacheTime, $value);
        } else {
            return $redis->set($key, $value);
        }
    }

    public function &instance($key = null, $isRead = true)
    {
        return $this->selectNode($key, $isRead);
    }
}
