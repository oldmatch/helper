<?php
namespace Oldmatch\Helper\Tests;

use Oldmatch\Helper\Service\RedisHelper;
use Oldmatch\Helper\Supports\Common;
use PHPUnit\Framework\TestCase;

class ServiceTest extends TestCase
{
    public function testRedis()
    {
        $config = [
            'host'   => 'host.docker.internal',
            'port'   => '6379',
            'password' => '',
            'select' => 0,
            'timeout' => 60,
        ];

        $res = RedisHelper::instance($config)->get('lkz1');
        $this->assertEquals($res, false);
    }

    public function testCommonCurl()
    {
        $res = Common::curlRequest('https://baidu.com');
        $this->assertEquals($res, false);
    }
}
