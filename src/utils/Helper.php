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
     * @return string
     */
    public static function key(): string
    {
        // 插件文件名
        return basename(Kitpress::getRootPath());
    }

    /**
     * 获取插件主文件
     * @return string
     * @throws \Exception
     */
    public static function getPluginFile(): string
    {

        $plugin_dir = Kitpress::getRootPath();

        // 插件文件名
        $plugin_name = self::key();

        $file_name = $plugin_dir . $plugin_name . '.php';
        // 如果没找到，抛出异常
        if( !file_exists($file_name) ){
            ErrorHandler::die(Lang::kit('框架路径错误：无法在 ' . $file_name . ' 目录下找到有效的插件主文件'));
        } 
        return $file_name;
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