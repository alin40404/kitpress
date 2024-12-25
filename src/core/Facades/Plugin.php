<?php
namespace kitpress\core\Facades;

use kitpress\core\abstracts\Facade;

if (!defined('ABSPATH')) {
    exit;
}

/**
* Plugin Facade 类
* 用于处理插件基本信息
*
* @method static void setNamespace(string $namespace) 设置插件命名空间
* @method static string getNamespace() 获取插件命名空间
* @method static string key() 获取插件根路径键名
* @method static string getRootPath() 获取插件根路径
* @method static string getRootFile() 获取插件根目录主文件
*
* @see \kitpress\library\Plugin 实际的插件功能实现类
*/
class Plugin extends Facade {
    protected static function getFacadeAccessor() {
        return 'plugin';
    }
}