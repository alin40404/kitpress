<?php
namespace kitpress\utils;

if (!defined('ABSPATH')) {
    exit;
}

class Helper{

    /**
     * 获取插件主文件路径
     * @return string
     * @throws \Exception
     */
    public static function getMainPluginFile() {

//        Log::error(Config::get('app'));
        Log::error(Config::get('app.plugin_path'));

        if( empty(Config::get('app.plugin_path')) ) ErrorHandler::die('请到配置文件 app.php 配置参数 plugin_path ：插件根目录');
        // 插件根目录
        $plugin_dir = Config::get('app.plugin_path');

        // 插件文件名
        $plugin_name = basename($plugin_dir);

        $file_name = $plugin_dir . $plugin_name . '.php';
        // 如果没找到，抛出异常
        if( !file_exists($file_name) ){
            wp_die('框架路径错误：无法在 ' . $file_name . ' 目录下找到有效的插件主文件');
        } 
        return $file_name;
    }

}