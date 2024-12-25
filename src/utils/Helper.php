<?php
namespace kitpress\utils;

use kitpress\Kitpress;

if (!defined('ABSPATH')) {
    exit;
}

class Helper{
    /**
     * 获取客户端真实 IP
     * @return string
     */
    public static function getClientIp()
    {
        // WordPress 6.2+ 推荐使用的方法
        if (function_exists('wp_get_remote_ip')) {
            return \wp_get_remote_ip();
        }

        $ip = null;
        // 如果在 wp-config.php 中定义了 HTTP_X_FORWARDED_FOR 处理
        if (defined('HTTP_X_FORWARDED_FOR') && HTTP_X_FORWARDED_FOR) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if (strpos($ip, ',') !== false) {
                // 如果包含多个 IP，取第一个
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }

        }

        return $ip ?: ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /**
     * 获取插件key，把插件文件夹名称作为插件的key
     * @param $rootPath
     * @return string
     */
    public static function key($rootPath): string
    {
        // 插件文件名
        return basename($rootPath);
    }

    public static function optionKey($key = '')
    {
       if(empty($key)) return '';
       return KITPRESS_NAME . '_' . self::key() . '_' . $key;
    }

    public static function getOption($key = ''){
        if(empty($key)) return '';
        return \get_option(self::optionKey($key));
    }
}