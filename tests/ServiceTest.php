<?php
namespace Oldmatch\Helper\Tests;

use Oldmatch\Helper\Service\ImageCompress;
use Oldmatch\Helper\Service\RedisHelper;
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

    public function compressImageTest()
    {
        $localImagePath = '';
        $image_compress_percent = 50;
        $localImagePath = '';
        // 压缩图片
        $ImageCompress = new ImageCompress($localImagePath, $image_compress_percent);
        $ImageCompress->compressImg($localImagePath);
    }
}
