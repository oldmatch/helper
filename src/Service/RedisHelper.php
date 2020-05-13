<?php

namespace Oldmatch\Helper\Service;

/*
 * redis助手类
 * @auth lkz <oldmatch@gmail.com>
 */

class RedisHelper
{
    protected static $instance; // 单例对象

    protected static $_Server;         // redis对象
    protected static $options = [];    // redis配置

    protected function __construct($options = [])
    {
        if (!extension_loaded('redis')) {
            throw new \BadFunctionCallException('not support: redis');
        }
        self::$options = array_merge(self::$options, $options);
        if (empty(self::$options)) {
            throw new \BadFunctionCallException('redis config: empty');
        }
        $func          = !empty(self::$options['persistent']) ? 'pconnect' : 'connect';
        self::$_Server = new \Redis;
        self::$_Server->$func(self::$options['host'], self::$options['port'], self::$options['timeout']);

        if ('' != self::$options['password']) {
            self::$_Server->auth(self::$options['password']);
        }

        if (0 != self::$options['select']) {
            self::$_Server->select(self::$options['select']);
        }
    }

    /**
     * @param array $options
     * 实例化对象
     * @return static
     * author lkz <oldmatch24@gmail.com>
     */
    public static function instance($options = [])
    {
        if (!empty(self::$options) && !empty($options)) {
            // 第二次调用，看看配置中的host，port,timeout,password是否改动
            if ((isset($options['host']) && $options['host'] != self::$options['host']) || (isset($options['port']) && $options['port'] != self::$options['port']) || (isset($options['timeout']) && $options['timeout'] != self::$options['timeout']) || (isset($options['password']) && $options['password'] != self::$options['password'])){
                self::$instance = new static($options); // redis配置改动，重新连接
            } else if ((isset($options['select']) && $options['select'] != self::$options['select'])){
                self::$_Server->select($options['select']); // 只是修改连接时select的库
                self::$options['select'] = $options['select'];
            }
        } else {
            if (is_null(self::$instance)) {
                self::$instance = new static($options);
            }
        }
        return self::$instance;
    }

    /**
     * 返回redis服务
     * @return \Redis
     * author lkz <oldmatch24@gmail.com>
     */
    public function getServer()
    {
        return self::$_Server;
    }

    /**
     * @param $select
     * 更改redis库，支持链式操作
     * @return mixed
     * author lkz <oldmatch24@gmail.com>
     */
    public function select($select)
    {
        self::$_Server->select($select);
        self::$options['select'] = $select;
        return self::$instance;
    }

    /**
     * 是否redis连接资源
     */
    public function close()
    {
        self::$_Server->close();
    }

    /**
     * @param      $lock_key
     * @param int  $lock_expire
     * @param bool $wait
     * 加锁
     * @return bool
     * author lkz <oldmatch24@gmail.com>
     */
    public function lock($lock_key, $lock_expire = 3, $wait = true)
    {
        $status = true;
        while ($status) {
            //设置锁的值为当前时间戳+有效期
            $lock_value = time() + $lock_expire;
            /**
             * 创建锁
             * 试图以$lockKey为key创建一个缓存,value值为当前时间戳
             * 由于setnx()函数只有在不存在当前key的缓存时才会创建成功
             * 所以，用此函数就可以判断当前执行的操作是否已经有其他进程在执行了
             * @var [type]
             */
            $lock = self::$_Server->setnx($lock_key, $lock_value);
            /**
             * 满足两个条件中的一个即可进行操作
             * 1、上面一步创建锁成功;
             * 2、   1）判断锁的值（时间戳）是否小于当前时间    $redis->get()
             *      2）同时给锁设置新值成功    $redis->getset()
             */
            if (!empty($lock) || (self::$_Server->get($lock_key) < time() && self::$_Server->getSet($lock_key, $lock_value) < time())) {
                //给锁设置生存时间
                self::$_Server->expire($lock_key, $lock_expire);
                return true;
            } else {
                if (!$wait) {
                    //如果是不等锁就直接返回false
                    return false;
                }
                sleep(1);//等待1秒后再尝试执行操作
            }
        }
        return false;
    }

    /**
     * 解锁
     * @param $lock_key
     */
    public function unlock($lock_key)
    {
        if(self::$_Server->ttl($lock_key))
            self::$_Server->del($lock_key);
    }

    /**
     * @param $key
     * 根据key获取值
     * @return bool|mixed|string
     * author lkz <oldmatch24@gmail.com>
     */
    public function get($key)
    {
        $value = self::$_Server->get($key);
        if (empty(json_decode($value, true))) {
                return $value;
        } else {
            return json_decode($value, true);
        }
    }

    /**
     * @param     $key
     * @param     $value
     * @param int $time
     * 根据key保存value
     * @return bool
     * author lkz <oldmatch24@gmail.com>
     */
    public function set($key, $value, $time = 0)
    {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        if (empty($time) || !is_int($time)) {
            return self::$_Server->set($key, $value);
        } else {
            return self::$_Server->set($key, $value, $time);
        }
    }

    /**
     * 删除某一个key值
     * @param $key
     */
    public function rm($key)
    {
        self::$_Server->del($key);
    }

    /**
     * @param $value
     * @param $list_key
     * 返回数据在列表中的排序，如果存在返回key，不存在则返回-1
     * @return false|int|string
     * author lkz <oldmatch24@gmail.com>
     */
    public function listGetKey($value, $list_key)
    {
        $arr = $this->get($list_key) ? : [];
        if (in_array($value, $arr)) {
            $key = array_search($value, $arr);
            return $key;
        } else {
            return -1;
        }
    }

    /**
     * @param $value
     * @param $list_key
     * 插入一个数据到列表中，如果存在直接返回key，不存在则插入value并且返回key
     * @return false|int|string
     * author lkz <oldmatch24@gmail.com>
     */
    public function listPushValue($value, $list_key)
    {
        $arr = $this->get($list_key) ? : [];
        if (in_array($value, $arr)) {
            $key = array_search($value, $arr);
            return $key;
        } else {
            array_push($arr, $value);
            $this->set($list_key, $arr);
            $key = array_search($value, $arr);
            return $key;
        }
    }

    /**
     * 把一个数据从列表中去掉
     * @param $value
     * @param $list_key
     */
    public function listRemoveValue($value, $list_key)
    {
        $arr = $this->get($list_key) ? : [];
        if (in_array($value, $arr)) {
            $temp = array_flip($arr);
            unset($temp[$value]);
            $arr = array_flip($temp);
            $arr = array_values($arr);
            $this->set($list_key, $arr);
        }
    }


    /**
     * @param string $key 队列key
     * @param string $value 插入内容
     * @param int $r 是否插入尾部 1--是 0--否(插入头部)
     * 进队
     * @return int
     * author lkz <oldmatch24@gmail.com>
     */
    public function push($key, $value, $r = 1)
    {
        if ($r === 1) {
            return self::$_Server->rPush($key, $value);
        } else {
            return self::$_Server->lPush($key, $value);
        }
    }

    /**
     * @param     $key
     * @param int $r 1--先进先出
     * 出队
     * @return string
     * author lkz <oldmatch24@gmail.com>
     */
    public function pop($key, $r = 1)
    {
        if ($r === 1) {
            return self::$_Server->lPop($key);
        } else {
            return self::$_Server->rPop($key);
        }
    }

    /**
     * @param $key
     * @param $index
     * 搜索队列
     * @return String
     * author lkz <oldmatch24@gmail.com>
     */
    public function indexList($key, $index)
    {
        return self::$_Server->lIndex($key, $index);
    }

    /**
     * @param $key
     * 获取list长度
     * @return int
     * author lkz <oldmatch24@gmail.com>
     */
    public function lengthList($key)
    {
        return self::$_Server->lLen($key);
    }


    /**
     * @param $key
     * @param $start
     * @param $end
     * 获取list数组
     * @return array
     * author lkz <oldmatch24@gmail.com>
     */
    public function getList($key, $start, $end)
    {
        return self::$_Server->lRange($key, $start, $end);
    }


    /**
     * @param $key
     * @param $hashKey
     * @param $value
     * hash类型存储
     * @return int
     * author lkz <oldmatch24@gmail.com>
     */
    public function hSet($key, $hashKey, $value)
    {
        return self::$_Server->hSet($key, $hashKey, $value);
    }

    /**
     * @param string $key
     * @param string $hashKey
     * hash获取数据
     * @return array|string
     * author lkz <oldmatch24@gmail.com>
     */
    public function hGet($key, $hashKey)
    {
        return self::$_Server->hGet($key, $hashKey);
    }
}
