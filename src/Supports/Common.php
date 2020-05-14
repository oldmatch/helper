<?php

namespace Oldmatch\Helper\Supports;

class Common
{
    /**
     * @param        $url
     * @param string $post
     * @param array  $data
     * @param array  $header
     * @param int    $time_limit
     * @param bool   $is_user_agent
     * curl请求
     * @return bool|string
     */
    public static function curlRequest($url, $post = 'get', $data = [], $header = [], $time_limit = 60, $is_user_agent = true)
    {
        $UserAgent = 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0; SLCC1; .NET CLR 2.0.50727; .NET CLR 3.0.04506; .NET CLR 3.5.21022; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';

        //初始化curl
        $ch = curl_init();
        //设置基本参数
        //设置返回值不直接输出
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);

        if ($is_user_agent === true) {
            curl_setopt($ch, CURLOPT_USERAGENT, $UserAgent);
        }

        //设置超时时长
        curl_setopt($ch, CURLOPT_TIMEOUT, $time_limit);

        if ($post == 'post') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        if (!empty($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $content = curl_exec($ch);
        curl_close($ch);
        return $content;
    }
}
