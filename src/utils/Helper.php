<?php
namespace kitpress\utils;

use kitpress\Kitpress;

if (!defined('ABSPATH')) {
    exit;
}

class Helper{

    /**
     * 获取插件根目录
     * @return null
     */
    protected static function getPluginRootPath()
    {
        // 插件根目录
        $plugin_dir = Kitpress::getRootPath();
        if( empty($plugin_dir) ) ErrorHandler::die(Lang::kit('插件根目录不正确'));
        return $plugin_dir;
    }

    /**
     * 获取插件key，把插件文件夹名称作为插件的key
     * @return string
     */
    public static function key() {
        $plugin_dir = self::getPluginRootPath();
        // 插件文件名
        return basename($plugin_dir);
    }

    /**
     * 获取插件主文件路径
     * @return string
     * @throws \Exception
     */
    public static function getMainPluginFile() {

        $plugin_dir = self::getPluginRootPath();

        // 插件文件名
        $plugin_name = self::key();

        $file_name = $plugin_dir . $plugin_name . '.php';
        // 如果没找到，抛出异常
        if( !file_exists($file_name) ){
            ErrorHandler::die(Lang::kit('框架路径错误：无法在 ' . $file_name . ' 目录下找到有效的插件主文件'));
        } 
        return $file_name;
    }




}